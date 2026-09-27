const COOKIE = 'quanta_last_project';

export function rememberLastProject(id: number): void {
    document.cookie = `${COOKIE}=${id};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`;
}

export function readLastProjectId(): number | null {
    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${COOKIE}=`));

    if (!match) {
        return null;
    }

    const value = Number(match.split('=')[1]);

    return Number.isFinite(value) ? value : null;
}
