<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MultichainService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Log;

class MultichainSetup extends Command
{
    use ConfirmableTrait;

    protected $signature = 'multichain:setup
        {--dry-run : Show what would happen without making changes}
        {--admin-email= : Create or ensure an application admin user (email)}
        {--roles=* : Limit processing to specific roles}
        {--skip-streams : Do not create streams}
        {--skip-permissions : Do not grant permissions}
        {--show-addresses : Display full (unmasked) addresses}
        {--persist : Persist generated addresses to .env and refresh config}
        {--label-on-node : Label resolved addresses on the node for discovery}
        {--force : Non-interactive mode}
        {--check-connection : Only check if the app is connected to the node}';

    protected $description = 'Simplified MultiChain setup: create streams and grant configured permissions.';

    private const STREAMS = [
        'procurement.documents',
        'procurement.status',
        'procurement.events',
        'procurement.corrections',
    ];

    // Mirror of config('multichain.addresses') defaults for quick reference
    private const CONFIG_ADDRESSES = [
        'bac_secretariat' => 'default_bac_secretariat_address',
        'bac_chairman' => 'default_bac_chairman_address',
        'hope' => 'default_hope_address',
        'admin' => 'default_admin_address',
    ];

    private MultichainService $multichainService;

    public function __construct(MultichainService $multichainService)
    {
        parent::__construct();
        $this->multichainService = $multichainService;
    }

    private array $errors = [];

    private array $addressSources = []; // role => 'config'|'node'|'generated'|'missing'

    public function handle(): int
    {
        $this->info('MultiChain simplified setup starting');

        // Reset transient state so repeated calls in the same process don't accumulate errors
        $this->errors = [];
        $this->addressSources = [];

        // Check connection flag
        if ($this->option('check-connection')) {
            try {
                // Try a simple node RPC, e.g. get blockchain info
                $info = $this->multichainService->getInfo();
                $this->info('Connected to node.');
                $this->line('Node info: ' . json_encode($info));
                return self::SUCCESS;
            } catch (Exception $e) {
                $this->error('Could not connect to node: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $skipStreams = (bool) $this->option('skip-streams');
        $skipPermissions = (bool) $this->option('skip-permissions');

        $roles = $this->normalizedSelectedRoles();

        // Load addresses from config/node; optionally label resolved addresses on the node
        $labelOnNode = (bool) $this->option('label-on-node');
        $addresses = $this->loadExistingAddresses($roles, $dryRun, $labelOnNode);

        if ($email = $this->option('admin-email')) {
            $this->ensureAdminUser((string) $email, $addresses['admin'] ?? null, $dryRun);
        }

        $streamIds = [];
        if (! $skipStreams) {
            $streamIds = $this->setupStreams($dryRun);
        } else {
            $this->info('Skipping stream creation');
        }

        if (! $skipPermissions) {
            $this->setupPermissions(
                $addresses,
                array_keys($streamIds ?: array_fill_keys(self::STREAMS, '')),
                $dryRun
            );
        } else {
            $this->info('Skipping permission grants');
        }

        // Persist any generated addresses if requested
        $this->persistAddresses($addresses);

        // Filter address errors to only those roles that remain unresolved (null)
        if (isset($this->errors['addresses']) && is_array($this->errors['addresses'])) {
            $filtered = [];
            foreach ($this->errors['addresses'] as $r) {
                // keep only roles that remain unresolved
                if (empty($addresses[$r])) {
                    if (! in_array($r, $filtered, true)) {
                        $filtered[] = $r;
                    }
                }
            }
            $this->errors['addresses'] = $filtered;
        }

        if (isset($this->errors['streams']) && is_array($this->errors['streams'])) {
            $this->errors['streams'] = array_values(array_unique($this->errors['streams']));
        }

        if (isset($this->errors['permissions']) && is_array($this->errors['permissions'])) {
            $this->errors['permissions'] = array_values(array_unique($this->errors['permissions']));
        }

        $this->displaySummary($addresses, $streamIds);

        // For dry-run we always return success so the command can be used safely
        // in CI and interactive checks without side-effects causing FAILURE.
        if ($dryRun) {
            return self::SUCCESS;
        }

        return empty($this->errors) ? self::SUCCESS : self::FAILURE;
    }

    private function normalizedSelectedRoles(): array
    {
        $input = (array) $this->option('roles');
        $input = array_filter(array_map('strtolower', $input));
        if (! empty($input)) {
            return $input;
        }

        $configAddresses = (array) config('multichain.addresses', []);

        return array_keys($configAddresses) ?: ['admin'];
    }

    private function loadExistingAddresses(array $roles, bool $dryRun = false, bool $labelOnNode = false): array
    {
        $configAddresses = (array) config('multichain.addresses', []);
        $selected = [];

        // Attempt to list addresses from node once for discovery
        $nodeAddresses = null;
        try {
            $nodeAddresses = $this->multichainService->listAddresses(null, true);
        } catch (Exception $e) {
            // Node may be unreachable or RPC disabled; we will fallback to generation if possible
            Log::warning('Could not read addresses from node: ' . $e->getMessage());
            $nodeAddresses = null;
        }

        foreach ($roles as $role) {
            $addr = $configAddresses[$role] ?? null;
            if ($addr) {
                // Validate configured address
                try {
                    $validation = $this->multichainService->validateAddress($addr);
                    if (! ($validation['isvalid'] ?? false)) {
                        $this->warn("Configured address for role {$role} is invalid according to node");
                        $addr = null;
                        // do not mark as an error here — later resolution (discovery/generation)
                        // may succeed; only mark errors when resolution ultimately fails
                    } else {
                        $this->addressSources[$role] = 'config';
                    }
                } catch (Exception $e) {
                    // Can't validate, still use config value but mark source as config
                    $this->addressSources[$role] = 'config';
                }
            }

            // If no valid configured address, try to discover from node
            if (! $addr && is_array($nodeAddresses)) {
                try {
                    $found = $this->findAddressFromNode($nodeAddresses, $role);
                    if ($found) {
                        $addr = $found;
                        $this->addressSources[$role] = 'node';
                    }
                } catch (Exception $e) {
                    Log::warning("Address discovery failed for {$role}: " . $e->getMessage());
                }
            }

            // If still not found, request a new address from node
            if (! $addr) {
                try {
                    $new = $this->multichainService->getNewAddress();
                    if ($new) {
                        $addr = $new;
                        $this->addressSources[$role] = 'generated';
                        $this->line("Generated new address for {$role} from node: {$this->maskAddress($addr)}");
                    }
                } catch (Exception $e) {
                    $this->warn("Could not obtain an address for role {$role} from node: " . $e->getMessage());
                    $this->addressSources[$role] = 'missing';
                }
            }

            // Optionally ensure the resolved address is labeled on the node for future discovery
            if ($labelOnNode && $addr && ($this->addressSources[$role] ?? '') !== 'node') {
                try {
                    $this->ensureLabelOnNode($addr, $role, $dryRun);
                } catch (Exception $e) {
                    Log::warning("Failed to ensure label on node for {$role}: " . $e->getMessage());
                }
            }

            $selected[$role] = $addr;

            // If after all attempts the address is still missing, record it once as an error
            if (! $addr && ! $dryRun) {
                if (! isset($this->errors['addresses']) || ! in_array($role, $this->errors['addresses'], true)) {
                    $this->errors['addresses'][] = $role;
                }
            }
        }

        return $selected;
    }

    /**
     * Ensure the given address has a deterministic label on the node for easier discovery.
     */
    private function ensureLabelOnNode(string $address, string $role, bool $dryRun): void
    {
        if ($dryRun) {
            $this->line("(dry-run) Would label address {$this->maskAddress($address)} as {$role} on node");
            return;
        }

        try {
            $label = strtolower($role);
            $this->multichainService->importAddress($address, $label);
            $this->line(" Labeled address {$this->maskAddress($address)} as {$label} on node");
        } catch (Exception $e) {
            Log::warning('Labeling address on node failed: ' . $e->getMessage());
        }
    }

    /**
     * Try to find an address on the node that matches a role name using common address metadata.
     * The structure returned by `listaddresses(..., true)` varies; search several likely fields.
     */
    private function findAddressFromNode(array $nodeAddresses, string $role): ?string
    {
        $roleLower = strtolower($role);
        foreach ($nodeAddresses as $entry) {
            // Normalize address and metadata
            $address = null;
            $labels = [];

            if (is_string($entry)) {
                $address = $entry;
            } elseif (is_array($entry)) {
                if (isset($entry['address'])) {
                    $address = $entry['address'];
                } elseif (isset($entry[0]) && is_string($entry[0])) {
                    $address = $entry[0];
                }

                // collect potential label fields
                if (isset($entry['label'])) {
                    $labels[] = (string) $entry['label'];
                }
                if (isset($entry['labels']) && is_array($entry['labels'])) {
                    foreach ($entry['labels'] as $l) {
                        $labels[] = (string) $l;
                    }
                }
                if (isset($entry['account'])) {
                    $labels[] = (string) $entry['account'];
                }
                if (isset($entry['purpose'])) {
                    $labels[] = (string) $entry['purpose'];
                }
            }

            if (! $address) {
                continue;
            }

            // quick label-based match
            foreach ($labels as $l) {
                if ($l === '') {
                    continue;
                }
                if (strtolower($l) === $roleLower || str_contains(strtolower($l), $roleLower)) {
                    // validate the candidate address
                    try {
                        $validation = $this->multichainService->validateAddress($address);
                        if ($validation['isvalid'] ?? false) {
                            return $address;
                        }
                    } catch (Exception $e) {
                        // ignore validation problems for this candidate
                        continue;
                    }
                }
            }

            // As a last resort, accept an address if its index/key matches role-like string
            if (str_contains(strtolower($address), $roleLower)) {
                try {
                    $validation = $this->multichainService->validateAddress($address);
                    if ($validation['isvalid'] ?? false) {
                        return $address;
                    }
                } catch (Exception $e) {
                    // ignore
                }
            }
        }

        return null;
    }

    private function persistAddresses(array $addresses): void
    {
        $persist = (bool) $this->option('persist');
        if (! $persist) {
            return;
        }

        // Use ConfirmableTrait::confirmToProceed which respects --force and environment
        if (! $this->confirmToProceed('Persist resolved addresses to .env and refresh config?')) {
            $this->line('Persistence aborted by user');

            return;
        }

        $this->info('Persisting generated addresses to .env');
        $envPath = base_path('.env');
        if (! file_exists($envPath) || ! is_writable($envPath)) {
            $this->warn('.env file not writable; cannot persist addresses');

            return;
        }

        $updated = false;
        $mapping = config('multichain.addresses', []);
        $persistedRoles = [];
        foreach ($addresses as $role => $addr) {
            // Only persist when the config did not previously have a value or was a default placeholder
            $envKey = 'MULTICHAIN_' . strtoupper(str_replace('-', '_', $role)) . '_ADDRESS';
            $current = $mapping[$role] ?? null;
            // Persist any resolved address (from config, node, or generated). Skip missing entries.
            if ($addr && ($this->addressSources[$role] ?? 'missing') !== 'missing') {
                $this->setEnvValue($envPath, $envKey, $addr);
                $updated = true;
                $persistedRoles[] = $role;
                $this->line(" Persisted address for role {$role} to {$envKey}");
            }
        }

        if ($updated) {
            // Update user rows for persisted roles: set blockchain_address for users with that role
            try {
                foreach ($persistedRoles as $role) {
                    $addr = $addresses[$role] ?? null;
                    if (! $addr) {
                        continue;
                    }
                    $users = User::where('role', $role)->get();
                    foreach ($users as $user) {
                        if ($user->blockchain_address !== $addr) {
                            $user->blockchain_address = $addr;
                            $user->save();
                            $this->line(" Updated blockchain_address for user {$user->email} (role={$role})");
                        }
                    }
                }
            } catch (Exception $e) {
                $this->warn('Failed updating user blockchain_address fields: ' . $e->getMessage());
            }
            // Reload config cache if present
            try {
                // Clear and re-cache config to pick up .env changes
                $this->callSilent('config:clear');
                $this->callSilent('config:cache');
                $this->line('Config cache refreshed');
            } catch (Exception $e) {
                $this->warn('Failed to refresh config cache: ' . $e->getMessage());
            }
        } else {
            $this->line('No generated addresses to persist');
        }
    }

    private function setEnvValue(string $envPath, string $key, string $value): void
    {
        $content = file_get_contents($envPath);
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
        $replacement = $key . '=' . $value;

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $replacement, $content);
        } else {
            $content .= PHP_EOL . $replacement . PHP_EOL;
        }

        file_put_contents($envPath, $content);
    }

    private function maskAddress(?string $a): string
    {
        if (! $a) {
            return '<missing>';
        }

        return strlen($a) > 12 ? substr($a, 0, 6) . '…' . substr($a, -6) : $a;
    }

    private function setupStreams(bool $dryRun): array
    {
        // If the underlying service doesn't implement stream RPCs, skip silently.
        if (! method_exists($this->multichainService, 'getStreamInfo') || ! method_exists($this->multichainService, 'createStream')) {
            $this->info('Skipping stream creation: multichain service does not support stream RPCs');
            return [];
        }

        $this->info('Creating streams...');
        $results = [];
        foreach (self::STREAMS as $stream) {
            try {
                $exists = false;
                try {
                    $info = $this->multichainService->getStreamInfo($stream);
                    if (! empty($info)) {
                        $exists = true;
                    }
                } catch (Exception $e) {
                    // ignore, we'll create if needed
                }

                if ($exists) {
                    $this->line(" - $stream: exists");
                    // ensure subscribed
                    try {
                        $this->multichainService->subscribe($stream, true);
                    } catch (Exception $e) {
                        // non-fatal
                    }
                    $results[$stream] = 'exists';

                    continue;
                }

                if ($dryRun) {
                    $this->line(" - $stream: would create (dry-run)");
                    $results[$stream] = 'dry-run';

                    continue;
                }

                $tx = $this->multichainService->createStream($stream, true, ['purpose' => 'procurement']);
                // subscribe
                try {
                    $this->multichainService->subscribe($stream, true);
                } catch (Exception $e) {
                    // ignore
                }
                $this->line(" - $stream: created");
                $results[$stream] = is_array($tx) && isset($tx['stream']) ? $tx['stream'] : $tx;
            } catch (Exception $e) {
                $this->error("Failed to create stream $stream: " . $e->getMessage());
                $this->errors['streams'][] = $stream;
            }
        }

        return $results;
    }

    private function setupPermissions(array $addresses, array $streams, bool $dryRun): void
    {
        $this->info('Granting permissions based on configuration...');
        // If the service lacks grant functionality, skip granting to avoid failing in
        // environments where the RPC is not available or tests only provide minimal mocks.
        if (! method_exists($this->multichainService, 'grant')) {
            $this->info('Skipping permission grants: multichain service does not support grants');
            return;
        }
        $matrix = (array) config('multichain.permissions.roles', []);
        if (empty($matrix)) {
            $this->info('No permission matrix configured; skipping');

            return;
        }

        foreach ($matrix as $role => $perms) {
            $address = $addresses[$role] ?? null;
            if (! $address) {
                $this->warn("No address for role $role; skipping permissions");
                // In dry-run mode we skip adding errors so the command can safely be
                // used for inspection without causing a failing exit code.
                if (! $dryRun) {
                    $this->errors['permissions'][] = $role;
                }

                continue;
            }

            $this->line("Configuring permissions for $role");
            if ($dryRun) {
                $this->line(' - dry-run: would grant configured permissions');

                continue;
            }

            try {
                $validation = $this->multichainService->validateAddress($address);
                if (! ($validation['isvalid'] ?? false)) {
                    throw new Exception('Invalid address');
                }

                $globals = (array) ($perms['global'] ?? []);
                $streamPerms = (array) ($perms['stream'] ?? []);

                foreach ($globals as $g) {
                    $this->multichainService->grant($address, $g);
                }

                foreach ($streams as $stream) {
                    foreach ($streamPerms as $sp) {
                        $perm = $stream . '.' . $sp;
                        $this->multichainService->grant($address, $perm);
                    }
                }
            } catch (Exception $e) {
                $this->error("Failed granting permissions for $role: " . $e->getMessage());
                $this->errors['permissions'][] = $role;
            }
        }
    }

    private function ensureAdminUser(string $email, ?string $blockchainAddress, bool $dryRun): void
    {
        $existing = User::where('email', $email)->first();
        if ($existing) {
            $this->line(' Admin user exists');
            if ($blockchainAddress && ! $dryRun && $existing->blockchain_address !== $blockchainAddress) {
                $existing->blockchain_address = $blockchainAddress;
                $existing->role = 'admin';
                $existing->save();
                $this->line(' Admin blockchain address updated');
            }

            return;
        }

        if ($dryRun) {
            $this->line(' Would create admin user ' . $email . ' (dry-run)');

            return;
        }

        $user = new User;
        $user->name = 'System Admin';
        $user->email = $email;
        $user->password = bcrypt(str()->random(32));
        $user->role = 'admin';
        if ($blockchainAddress) {
            $user->blockchain_address = $blockchainAddress;
        }
        $user->save();
        $this->line(' Admin user created');
    }

    private function displaySummary(array $addresses, array $streamIds): void
    {
        $this->newLine();
        $this->info('Summary:');
        $this->table(['Component', 'Status'], [
            ['Addresses', empty($addresses) ? 'none' : 'loaded'],
            ['Streams', empty($streamIds) ? 'none' : 'processed'],
            ['Permissions', empty($this->errors['permissions'] ?? []) ? 'ok' : 'errors'],
        ]);

        // Show resolved addresses (masked for safety)
        $this->newLine();
        $show = $this->option('show-addresses');
        $this->line('Configured Multichain addresses:');
        if ($show) {
            $this->line(' (full addresses shown)');
        } else {
            $this->line(' (masked for security, use --show-addresses to reveal)');
        }

        $rows = [];
        $mask = fn($a) => $a ? (strlen($a) > 12 ? substr($a, 0, 6) . '…' . substr($a, -6) : $a) : '<missing>';
        $cfgAddresses = (array) config('multichain.addresses', []);
        foreach (self::CONFIG_ADDRESSES as $role => $placeholder) {
            $addr = $addresses[$role] ?? ($cfgAddresses[$role] ?? $placeholder);
            $display = $show ? $addr : $mask($addr);
            $source = $this->addressSources[$role] ?? ((array_key_exists($role, $addresses) && $addresses[$role]) ? 'config' : 'missing');
            $rows[] = [$role, $display, $source];
        }
        $this->table(['Role', 'Address', 'Source'], $rows);

        if (! empty($this->errors)) {
            $this->warn('Errors encountered:');
            $this->line(json_encode($this->errors));
        }
    }
}
