<?php

namespace Module;

use App\Providers\DashboardServiceProvider;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            // Auth user
            'auth' => function () use ($request) {
                $user = $request->user();

                return $user ? [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name ?? $user->username,
                        'email' => $user->email,
                        'username' => $user->username,
                        'avatar' => $user->avatar ?? null,
                        'verified' => (bool) $user->verified,
                        'country' => $user->userCountry?->name ?? 'FR',
                        'wants_men' => (bool) $user->wants_men,
                        'wants_women' => (bool) $user->wants_women,
                        'wants_trans' => (bool) $user->wants_trans,
                        'wallet_balance' => DashboardServiceProvider::calculCredit($user),
                    ],
                    'is_admin' => (bool) $user->admin,
                    'role' => $user->userRole->name ?? 'fan',
                ] : null;
            },

            // Flash messages
            'flash' => function () use ($request) {
                return [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                    'warning' => $request->session()->get('warning'),
                    'info' => $request->session()->get('info'),
                ];
            },

            // Errors
            'errors' => function () use ($request) {
                return $request->session()->get('errors')
                    ? $request->session()->get('errors')->getBag('default')->getMessages()
                    : (object) [];
            },
            // App config
            'app' => [
                'name' => config('app.name'),
                'locale' => app()->getLocale(),
                'aws' => [
                    'url' => config('filesystems.disks.s3.url') ?? config('filesystems.disks.s3.endpoint'),
                ],
            ],

            // Pusher config (for real-time notifications)
            'pusher' => function () use ($request) {
                if (!$request->user()) return null;
                return [
                    'key' => getSetting('websockets.pusher_app_key'),
                    'cluster' => getSetting('websockets.pusher_app_cluster'),
                ];
            },
        ]);
    }
}
