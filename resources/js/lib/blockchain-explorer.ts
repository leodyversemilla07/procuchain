export function formatBlockchainDate(timestamp: number): string {
    const date = new Date(timestamp * 1000);

    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

export function formatBytes(bytes: number): string {
    if (bytes === 0) {
        return '0 Bytes';
    }

    const kilobyte = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const index = Math.floor(Math.log(bytes) / Math.log(kilobyte));

    return `${Math.round((bytes / Math.pow(kilobyte, index)) * 100) / 100} ${sizes[index]}`;
}

export function truncateHash(hash: string, length: number = 10): string {
    return hash.length > length ? `${hash.substring(0, length)}...` : hash;
}

export function formatPingTime(pingtime?: number): string {
    if (!pingtime) {
        return 'N/A';
    }

    return `${(pingtime * 1000).toFixed(2)}ms`;
}

export function getSyncStatus(syncedBlocks: number, startingHeight: number): string {
    if (syncedBlocks >= startingHeight) {
        return 'Fully Synced';
    }

    return `${syncedBlocks}/${startingHeight} blocks`;
}
