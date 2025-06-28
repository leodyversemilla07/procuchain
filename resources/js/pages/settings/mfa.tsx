import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Shield, Smartphone, Key, AlertTriangle, Download, RefreshCw, Copy, Check } from 'lucide-react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Multi-Factor Authentication',
        href: '/settings/mfa',
    },
];

interface MfaProps {
    mfaEnabled: boolean;
    backupCodesCount: number;
    status?: string;
    backupCodes?: string[];
}

export default function Mfa({ mfaEnabled, backupCodesCount, status, backupCodes }: MfaProps) {
    const [qrCodeUrl, setQrCodeUrl] = useState<string>('');
    const [secret, setSecret] = useState<string>('');
    const [showSetup, setShowSetup] = useState(false);
    const [showBackupCodes, setShowBackupCodes] = useState(false);
    const [displayBackupCodes, setDisplayBackupCodes] = useState<string[]>(backupCodes || []);
    const [copiedCodes, setCopiedCodes] = useState<boolean>(false);    const setupForm = useForm({
        code: '',
        password: '',
    });

    const disableForm = useForm({
        code: '',
        password: '',
    });

    const backupCodesForm = useForm({
        password: '',
    });    
    
    const handleSetupMfa = async () => {
        try {
            const response = await fetch('/settings/mfa/setup', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (response.ok) {
                const data = await response.json();
                setQrCodeUrl(data.qrCodeUrl);
                setSecret(data.secret);
                setShowSetup(true);
            } else {
                console.error('Setup failed with status:', response.status);
                const errorData = await response.text();
                console.error('Error response:', errorData);
            }
        } catch (error) {
            console.error('Failed to setup MFA:', error);
        }
    };

    const submitSetup: FormEventHandler = (e) => {
        e.preventDefault();
        setupForm.post(route('mfa.enable'), {
            onSuccess: () => {
                setShowSetup(false);
                setupForm.reset();
            },
        });
    };

    const submitDisable: FormEventHandler = (e) => {
        e.preventDefault();
        disableForm.post(route('mfa.disable'), {
            onSuccess: () => {
                disableForm.reset();
            },
        });
    };

    const handleRegenerateBackupCodes: FormEventHandler = (e) => {
        e.preventDefault();
        
        fetch('/settings/mfa/backup-codes/regenerate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ password: backupCodesForm.data.password }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.backupCodes) {
                setDisplayBackupCodes(data.backupCodes);
                setShowBackupCodes(true);
                backupCodesForm.reset();
            }
        })
        .catch(error => {
            console.error('Failed to regenerate backup codes:', error);
        });
    };

    const copyBackupCodes = () => {
        const codes = displayBackupCodes.join('\n');
        navigator.clipboard.writeText(codes).then(() => {
            setCopiedCodes(true);
            setTimeout(() => setCopiedCodes(false), 2000);
        });
    };

    const downloadBackupCodes = () => {
        const codes = displayBackupCodes.join('\n');
        const blob = new Blob([codes], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'procuchain-backup-codes.txt';
        a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Multi-Factor Authentication" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall 
                        title="Multi-Factor Authentication" 
                        description="Add an extra layer of security to your account" 
                    />                    
                    {/* Status Messages */}
                    {status === 'mfa-enabled' && displayBackupCodes.length > 0 && (
                        <Alert className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950/30">
                            <Shield className="h-4 w-4 text-green-600 dark:text-green-400" />
                            <AlertDescription className="text-green-700 dark:text-green-300">
                                <div className="flex items-start space-x-2">
                                    <div className="flex-1">
                                        <div className="font-semibold mb-1">Multi-Factor Authentication Enabled!</div>
                                        <div className="text-sm">
                                            Your account is now protected with an additional layer of security. 
                                            Please save your backup codes below in a secure location.
                                        </div>
                                    </div>
                                </div>
                            </AlertDescription>
                        </Alert>
                    )}

                    {status === 'mfa-disabled' && (
                        <Alert className="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/30">
                            <Shield className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            <AlertDescription className="text-blue-700 dark:text-blue-300">
                                <div className="flex items-start space-x-2">
                                    <div className="flex-1">
                                        <div className="font-semibold mb-1">Multi-Factor Authentication Disabled</div>
                                        <div className="text-sm">
                                            MFA has been successfully disabled for your account. Your backup codes have been invalidated.
                                            You can re-enable MFA at any time for enhanced security.
                                        </div>
                                    </div>
                                </div>
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* MFA Status Card */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center space-x-2">
                                        <Shield className="h-5 w-5" />
                                        <span>Authentication Status</span>
                                    </CardTitle>
                                    <CardDescription>
                                        Current multi-factor authentication settings
                                    </CardDescription>
                                </div>
                                <Badge variant={mfaEnabled ? "default" : "secondary"}>
                                    {mfaEnabled ? "Enabled" : "Disabled"}
                                </Badge>
                            </div>
                        </CardHeader>                        
                        <CardContent>
                            {mfaEnabled ? (
                                <div className="space-y-4">
                                    <div className="flex items-center space-x-2 text-sm text-green-600 dark:text-green-400">
                                        <Check className="h-4 w-4" />
                                        <span>Your account is protected with multi-factor authentication</span>
                                    </div>
                                    
                                    {backupCodesCount > 0 && (
                                        <div className="text-sm text-muted-foreground">
                                            You have {backupCodesCount} backup code{backupCodesCount !== 1 ? 's' : ''} remaining
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="flex items-center space-x-2 text-sm text-amber-600 dark:text-amber-400">
                                    <AlertTriangle className="h-4 w-4" />
                                    <span>Your account is not protected with multi-factor authentication</span>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Enable MFA */}
                    {!mfaEnabled && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center space-x-2">
                                    <Smartphone className="h-5 w-5" />
                                    <span>Enable Multi-Factor Authentication</span>
                                </CardTitle>
                                <CardDescription>
                                    Use an authenticator app like Google Authenticator or Authy to generate verification codes
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {!showSetup ? (
                                    <Button onClick={handleSetupMfa}>
                                        Get Started
                                    </Button>
                                ) : (
                                    <div className="space-y-6">
                                        <div className="text-center space-y-4">
                                            <h3 className="text-lg font-medium">Scan QR Code</h3>
                                            <p className="text-sm text-muted-foreground">
                                                Scan this QR code with your authenticator app
                                            </p>
                                            
                                            {qrCodeUrl && (
                                                <div className="flex justify-center">
                                                    <img 
                                                        src={qrCodeUrl} 
                                                        alt="QR Code" 
                                                        className="border border-border rounded-lg"
                                                    />
                                                </div>
                                            )}
                                            
                                            <div className="text-center">
                                                <p className="text-xs text-muted-foreground mb-2">
                                                    Or manually enter this secret key:
                                                </p>
                                                <code className="bg-muted px-2 py-1 rounded text-sm">
                                                    {secret}
                                                </code>
                                            </div>
                                        </div>

                                        <Separator />

                                        <form onSubmit={submitSetup} className="space-y-4">
                                            <div className="space-y-2 flex flex-col items-center">
                                                <Label htmlFor="code">Verification Code</Label>
                                                <InputOTP
                                                    id="code"
                                                    maxLength={6}
                                                    value={setupForm.data.code}
                                                    onChange={(value: string) => setupForm.setData('code', value)}
                                                    disabled={setupForm.processing}
                                                    className="mx-auto"
                                                >
                                                    <InputOTPGroup>
                                                        <InputOTPSlot index={0} />
                                                        <InputOTPSlot index={1} />
                                                        <InputOTPSlot index={2} />
                                                        <InputOTPSlot index={3} />
                                                        <InputOTPSlot index={4} />
                                                        <InputOTPSlot index={5} />
                                                    </InputOTPGroup>
                                                </InputOTP>
                                                <p className="text-xs text-muted-foreground">
                                                    Enter the 6-digit code from your authenticator app
                                                </p>
                                                <InputError message={setupForm.errors.code} />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="password">Confirm Password</Label>
                                                <Input
                                                    id="password"
                                                    type="password"
                                                    placeholder="Current password"
                                                    value={setupForm.data.password}
                                                    onChange={(e) => setupForm.setData('password', e.target.value)}
                                                />
                                                <InputError message={setupForm.errors.password} />
                                            </div>

                                            <div className="flex space-x-2">
                                                <Button type="submit" disabled={setupForm.processing}>
                                                    {setupForm.processing ? 'Enabling...' : 'Enable MFA'}
                                                </Button>
                                                <Button 
                                                    type="button" 
                                                    variant="outline" 
                                                    onClick={() => setShowSetup(false)}
                                                >
                                                    Cancel
                                                </Button>
                                            </div>
                                        </form>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Disable MFA */}
                    {mfaEnabled && (
                        <Card className="border-destructive/20">
                            <CardHeader>
                                <CardTitle className="text-destructive">Disable Multi-Factor Authentication</CardTitle>
                                <CardDescription>
                                    Remove the extra security layer from your account
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitDisable} className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="disable_code">Verification Code or Backup Code</Label>
                                        <Input
                                            id="disable_code"
                                            type="text"
                                            placeholder="000000 or backup code"
                                            value={disableForm.data.code}
                                            onChange={(e) => disableForm.setData('code', e.target.value)}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Enter a 6-digit code from your authenticator app or a backup code
                                        </p>
                                        <InputError message={disableForm.errors.code} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="disable_password">Confirm Password</Label>
                                        <Input
                                            id="disable_password"
                                            type="password"
                                            placeholder="Current password"
                                            value={disableForm.data.password}
                                            onChange={(e) => disableForm.setData('password', e.target.value)}
                                        />
                                        <InputError message={disableForm.errors.password} />
                                    </div>

                                    <Button 
                                        type="submit" 
                                        variant="destructive" 
                                        disabled={disableForm.processing}
                                    >
                                        {disableForm.processing ? 'Disabling...' : 'Disable MFA'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    )}

                    {/* Backup Codes */}
                    {mfaEnabled && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center space-x-2">
                                    <Key className="h-5 w-5" />
                                    <span>Backup Codes</span>
                                </CardTitle>
                                <CardDescription>
                                    Use these codes to access your account if you lose your authenticator device
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="text-sm text-muted-foreground">
                                        You have {backupCodesCount} backup code{backupCodesCount !== 1 ? 's' : ''} remaining.
                                        Each code can only be used once.
                                    </div>

                                    <form onSubmit={handleRegenerateBackupCodes} className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="backup_password">Confirm Password</Label>
                                            <Input
                                                id="backup_password"
                                                type="password"
                                                placeholder="Current password"
                                                value={backupCodesForm.data.password}
                                                onChange={(e) => backupCodesForm.setData('password', e.target.value)}
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Enter your password to generate new backup codes
                                            </p>
                                            <InputError message={backupCodesForm.errors.password} />
                                        </div>

                                        <Button 
                                            type="submit" 
                                            variant="outline"
                                            disabled={backupCodesForm.processing}
                                        >
                                            <RefreshCw className="h-4 w-4 mr-2" />
                                            {backupCodesForm.processing ? 'Generating...' : 'Generate New Codes'}
                                        </Button>
                                    </form>
                                </div>
                            </CardContent>
                        </Card>
                    )}                    {/* Display Backup Codes */}
                    {(displayBackupCodes.length > 0 || showBackupCodes) && (
                        <Card className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30">
                            <CardHeader>
                                <CardTitle className="text-amber-800 dark:text-amber-200 flex items-center space-x-2">
                                    <Key className="h-5 w-5" />
                                    <span>Your Backup Codes</span>
                                </CardTitle>
                                <CardDescription className="text-amber-700 dark:text-amber-300">
                                    Save these codes in a safe place. You won't be able to see them again.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-6">
                                    {/* Backup Codes Grid */}
                                    <div className="relative">
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 font-mono text-sm bg-muted/50 dark:bg-muted/20 p-6 rounded-lg border border-border/50">
                                            {displayBackupCodes.map((code, index) => (
                                                <div 
                                                    key={index} 
                                                    className="flex items-center justify-between p-3 bg-background dark:bg-background/50 rounded-md border border-border/30 hover:border-border/60 transition-colors"
                                                >
                                                    <span className="text-foreground font-medium tracking-wider">
                                                        {code}
                                                    </span>
                                                    <div className="text-xs text-muted-foreground ml-3">
                                                        #{(index + 1).toString().padStart(2, '0')}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                        
                                        {/* Subtle watermark overlay */}
                                        <div className="absolute inset-0 pointer-events-none flex items-center justify-center opacity-5">
                                            <Key className="h-32 w-32 text-muted-foreground" />
                                        </div>
                                    </div>

                                    {/* Action Buttons */}
                                    <div className="flex flex-wrap gap-3">
                                        <Button 
                                            type="button" 
                                            variant="outline" 
                                            onClick={copyBackupCodes}
                                            className="flex items-center space-x-2"
                                        >
                                            {copiedCodes ? (
                                                <>
                                                    <Check className="h-4 w-4 text-green-600 dark:text-green-400" />
                                                    <span>Copied!</span>
                                                </>
                                            ) : (
                                                <>
                                                    <Copy className="h-4 w-4" />
                                                    <span>Copy Codes</span>
                                                </>
                                            )}
                                        </Button>
                                        
                                        <Button 
                                            type="button" 
                                            variant="outline" 
                                            onClick={downloadBackupCodes}
                                            className="flex items-center space-x-2"
                                        >
                                            <Download className="h-4 w-4" />
                                            <span>Download</span>
                                        </Button>
                                    </div>

                                    {/* Enhanced Warning Alert */}
                                    <Alert className="border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/20">
                                        <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                        <AlertDescription className="text-amber-800 dark:text-amber-200">
                                            <div className="space-y-2">
                                                <div className="font-semibold">Important Security Notice:</div>
                                                <ul className="text-sm space-y-1 ml-4 list-disc">
                                                    <li>Store these codes securely in a password manager or safe location</li>
                                                    <li>Each code can only be used once to access your account</li>
                                                    <li>These are your only recovery method if you lose your authenticator device</li>
                                                    <li>Keep them separate from your primary device for maximum security</li>
                                                </ul>
                                            </div>
                                        </AlertDescription>
                                    </Alert>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
