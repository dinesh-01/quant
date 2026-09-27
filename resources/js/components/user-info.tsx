import { UserAvatar } from '@/components/chrome/stat-card';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const role =
        typeof user.role === 'object' &&
        user.role !== null &&
        'name' in user.role &&
        typeof user.role.name === 'string'
            ? user.role.name
            : null;

    return (
        <>
            <UserAvatar name={user.name} size="lg" />
            <div className="grid flex-1 text-left leading-tight">
                <span className="truncate text-[13px] font-semibold text-white">
                    {user.name}
                </span>
                <span className="truncate text-[11px] text-[#7c8598]">
                    {showEmail ? user.email : (role ?? user.email)}
                </span>
            </div>
        </>
    );
}
