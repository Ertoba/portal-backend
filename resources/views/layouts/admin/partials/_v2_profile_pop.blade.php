{{-- Shared v2 profile popover (rail bottom). --}}
@php($admin_user = auth('admin')->user())
<div class="v2-profile-pop" id="v2-profile-pop" role="menu">
    <div class="v2-profile-pop-head">
        <span class="v2-avatar v2-avatar--lg">{{ strtoupper(substr($admin_user->f_name ?? 'A', 0, 1) . substr($admin_user->l_name ?? '', 0, 1)) }}</span>
        <div class="v2-meta">
            <div class="v2-name">{{ trim(($admin_user->f_name ?? '') . ' ' . ($admin_user->l_name ?? '')) ?: 'Admin' }}</div>
            <div class="v2-email">{{ $admin_user->email ?? '' }}</div>
        </div>
    </div>
    <a class="v2-profile-pop-item" href="{{ route('admin.settings') }}">
        <i data-lucide="user-cog"></i><span>{{ translate('Profile settings') }}</span>
    </a>
    <button type="button" class="v2-profile-pop-item v2-profile-pop-item--danger log-out">
        <i data-lucide="log-out"></i><span>{{ translate('Log out') }}</span>
    </button>
</div>
