<?php

namespace Module\Profile\Controller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MessengerController;
use App\Model\Country;
use App\Providers\AttachmentServiceProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\ListsHelperServiceProvider;
use App\Providers\PostsHelperServiceProvider;
use App\Providers\StreamsServiceProvider;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;
use Intervention\Image\Facades\Image;
use Module\Post\DTO\PostQueryParams;
use Module\Post\Model\Post;
use Module\Post\Resource\PaginationResource;
use Module\Post\Resource\PostResource;
use Module\Post\Service\PostQueryService;
use Module\Profile\Helpers\AttachmentHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\Uid\Uuid;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Profile Controller (Inertia-based)
 *
 * Handles user profile pages with Inertia.js + React frontend
 */
class ProfileController extends Controller
{
    protected ?User $user;
    protected bool $hasSub = false;
    protected bool $isOwner = false;
    protected bool $isPublic = false;
    protected bool $isFollowing = false;
    protected bool $viewerHasChatAccess = false;

    public function __construct(Request $request)
    {
        // Only fetch user if username parameter exists in route
        $username = $request->route('username');
        if ($username) {
            $this->user = PostsHelperServiceProvider::getUserByUsername($username);
        }
    }

    /**
     * Get profile data by username (API endpoint).
     * Returns JSON user data for API consumption (separate from Inertia page).
     *
     * @param Request $request
     * @param string $username
     * @return JsonResponse
     */
    public function show(Request $request, string $username): JsonResponse
    {
        if ($response = $this->validateProfileAccess()) {
            return $response;
        }

        $userData = $this->buildUserData();
        $relationship = $this->buildRelationship();
        if ($relationship) {
            $userData['relationship'] = $relationship;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $userData,
                'has_sub' => $this->hasSub,
                'viewer_has_chat_access' => $this->viewerHasChatAccess,
                'offer' => $this->calculateOfferForApi(),
            ],
        ]);
    }

    /**
     * Renders the main profile page with Inertia.
     *
     * @param Request $request
     * @return JsonResponse|Response|\Symfony\Component\HttpFoundation\Response
     */
    public function index(Request $request)
    {
        if (!$this->user) {
            return $this->inertiaError($request, 404, __('Profile not found.'));
        }

        if (!$this->user->is_active) {
            return $this->inertiaError($request, 412, __('Profile not found.'));
        }

        $this->setAccessRules();
        if (!$this->user->public_profile && !Auth::check()) {
            return $this->inertiaError($request, 403, __('Profile access is denied.'));
        }

        if ($this->isGeoLocationBlocked()) {
            return $this->inertiaError($request, 403, __('Profile access is denied.'));
        }

        if (GenericHelperServiceProvider::hasUserBlocked($this->user->id, Auth::user()?->id)) {
            return $this->inertiaError($request, 403, __('Profile access is denied.'));
        }

        $mediaType = $request->get('filter') ?? 'media';
        $service = new PostQueryService();
        $params = PostQueryParams::make()
            ->forUser($this->user->id)
            ->viewedBy(Auth::user())
            ->withMediaType($mediaType)
            ->withSortBy('latest')
            ->withSortOrder('desc')
            ->page((int) $request->get('page', 1))
            ->perPage(15);

        $posts = $service->profilePosts($params);
        $postsData = PostResource::collection($posts);

        // Calculate offers — inline camelCase mapping for Inertia
        $offerApi = $this->calculateOfferForApi();
        $offer = $offerApi ? [
            'discountAmount' => $offerApi['discount_amount'] ?? [],
            'daysRemaining' => $offerApi['days_remaining'] ?? null,
            'expiresAt' => $offerApi['expires_at'] ?? null,
        ] : [];

        // Filter type counts
        $filterTypeCounts = PostsHelperServiceProvider::getUserFilterTypesCount($this->user->id);

        // Streams data
        $streams = null;
        $streamsData = [];
        if ($mediaType == 'streams') {
            $streams = StreamsServiceProvider::getPublicStreams(['userId' => $this->user->id, 'status' => 'all']);
            $streamsData = $streams->map(function ($stream) {
                return [
                    'id' => $stream->id,
                    'title' => $stream->title,
                    'status' => $stream->status,
                    'scheduled_at' => $stream->scheduled_at?->toDateTimeString(),
                ];
            })->toArray();
        }

        $hasActiveStream = StreamsServiceProvider::getUserInProgressStream(true, $this->user->id) !== null;

        $paginatorConfig = $this->buildPaginatorConfig($posts, 'posts');
        if ($mediaType == 'streams' && $streams) {
            $paginatorConfig = $this->buildPaginatorConfig($streams, 'streams');
        }

        return Inertia::render('Profile', [
            'user' => $this->buildUserData(),
            'has_sub' => $this->hasSub,
            'is_following' => $this->isFollowing,
            'has_messenger' => MessengerController::checkMessengerAccess(Auth::user()?->id, $this->user?->id) ?? false,
            'is_owner' => $this->isOwner,
            'viewer_has_chat_access' => $this->viewerHasChatAccess,
            'posts' => $postsData,
            'active_filter' => $mediaType,
            'filter_type_counts' => $filterTypeCounts,
            'posts_total' => PostsHelperServiceProvider::numberPublicPostUser($this->user->id),
            'offer' => $offer,
            'streams' => $streamsData,
            'has_active_stream' => $hasActiveStream,
            'recent_media' => $this->getRecentMedia(),
            'seo_description' => $this->buildSeoDescription(),
            'paginator_config' => $paginatorConfig,
            'media_settings' => [
                'allowed_file_extensions' => '.' . str_replace(',', ',.', AttachmentServiceProvider::filterExtensions('all')),
                'max_file_upload_size' => (int) getSetting('media.max_file_upload_size'),
                'use_chunked_uploads' => (bool) getSetting('media.use_chunked_uploads'),
                'upload_chunk_size' => (int) getSetting('media.upload_chunk_size'),
                'max_post_description_size' => (int) getSetting('feed.min_post_description'),
            ],
        ]);
    }

    /**
     * Validate profile access for API requests.
     * Returns JSON error response if access is denied, null if access is granted.
     *
     * @return \Illuminate\Http\JsonResponse|null
     */
    protected function validateProfileAccess(): ?\Illuminate\Http\JsonResponse
    {
        if (!$this->user) {
            return response()->json([
                'success' => false,
                'message' => __('Profile not found.'),
            ], 404);
        }

        if (!$this->user->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('Profile not found.'),
            ], 412);
        }

        $this->setAccessRules();

        if (!$this->user->public_profile && !Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => __('Profile access is denied.'),
            ], 403);
        }

        if ($this->isGeoLocationBlocked()) {
            return response()->json([
                'success' => false,
                'message' => __('Profile access is denied.'),
            ], 403);
        }

        if (GenericHelperServiceProvider::hasUserBlocked($this->user->id, Auth::user()?->id)) {
            return response()->json([
                'success' => false,
                'message' => __('Profile access is denied.'),
            ], 403);
        }

        return null; // Access granted
    }

    /**
     * Fetches user posts for pagination (API).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserPosts(Request $request)
    {
        if ($response = $this->validateProfileAccess()) {
            return $response;
        }

        $service = new PostQueryService();
        $params = PostQueryParams::make()
            ->forUser($this->user->id)
            ->viewedBy(Auth::user())
            ->withMediaType($request->get('filter', 'media'))
            ->withSearch($request->get('search'))
            ->withSortBy($request->get('sort_by', 'latest'))
            ->withSortOrder($request->get('sort_order', 'desc'))
            ->page((int) $request->get('page', 1));

        if ($request->has('per_page')) {
            $params->perPage((int) $request->get('per_page'));
        }

        $posts = $service->profilePosts($params);

        // Compute media type counts (ignoring current filter/search)
        $counts = $this->buildPostCounts($this->user->id);

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => PostResource::collection($posts),
                'pagination' => PaginationResource::format($posts, '/profile/' . $this->user->username . '/posts'),
                'counts' => $counts,
            ],
        ]);
    }

    /**
     * Fetches paginated user streams (API).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserStreams(Request $request)
    {
        if ($response = $this->validateProfileAccess()) {
            return $response;
        }

        $page = $request->get('page', 1);

        $streams = StreamsServiceProvider::getPublicStreams([
            'userId' => $this->user->id,
            'status' => 'all',
            'encodePostsToHtml' => false,
            'showUsername' => false,
            'page' => $page,
        ]);

        // Handle both array and collection return types
        $streamsCollection = is_array($streams) ? collect($streams) : $streams;
        $streamsData = $streamsCollection->map(function ($stream) {
            return [
                'id' => $stream->id,
                'title' => $stream->title,
                'status' => $stream->status,
                'scheduled_at' => $stream->scheduled_at?->toDateTimeString(),
            ];
        })->toArray();

        $basePath = '/profile/' . $this->user->username . '/streams';
        $pagination = $streams instanceof LengthAwarePaginator
            ? PaginationResource::format($streams, $basePath)
            : [
                'current_page' => (int) $page,
                'per_page' => count($streamsData),
                'total' => count($streamsData),
                'has_more' => false,
                'next_page_url' => null,
                'prev_page_url' => null,
            ];

        return response()->json([
            'success' => true,
            'data' => [
                'streams' => $streamsData,
                'pagination' => $pagination,
            ],
        ]);
    }

    /**
     * Get recent activity for the authenticated user (MOCK DATA).
     * Returns mock activity feed for development.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActivity(Request $request)
    {
        // Authentication handled by jwt.auth middleware
        $user = JWTAuth::user();

        $limit = min($request->get('limit', 10), 50);

        // Mock activity data for development
        $activities = [
            [
                'id' => 'activity_1',
                'type' => 'like',
                'content' => 'liked your post',
                'created_at' => now()->subMinutes(5)->toDateTimeString(),
                'user' => [
                    'id' => 123,
                    'username' => 'alice_user',
                    'name' => 'Alice',
                    'avatar' => null,
                    'is_verified' => true,
                ],
            ],
            [
                'id' => 'activity_2',
                'type' => 'follow',
                'content' => 'started following you',
                'created_at' => now()->subMinutes(15)->toDateTimeString(),
                'user' => [
                    'id' => 456,
                    'username' => 'bob_creates',
                    'name' => 'Bob',
                    'avatar' => null,
                    'is_verified' => false,
                ],
            ],
            [
                'id' => 'activity_3',
                'type' => 'comment',
                'content' => 'commented: "Amazing! 🔥"',
                'created_at' => now()->subHours(1)->toDateTimeString(),
                'user' => [
                    'id' => 789,
                    'username' => 'charlie_dev',
                    'name' => 'Charlie',
                    'avatar' => null,
                    'is_verified' => false,
                ],
            ],
            [
                'id' => 'activity_4',
                'type' => 'post',
                'content' => 'created a new post',
                'created_at' => now()->subHours(3)->toDateTimeString(),
            ],
            [
                'id' => 'activity_5',
                'type' => 'media',
                'content' => 'uploaded 3 media files',
                'created_at' => now()->subDays(1)->toDateTimeString(),
            ],
        ];

        // Limit results
        $activities = array_slice($activities, 0, $limit);

        return response()->json([
            'success' => true,
            'data' => [
                'activities' => $activities,
            ],
        ]);
    }

    /**
     * Upload and update user's profile avatar.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        return $this->uploadImage($request, 'avatar');
    }

    /**
     * Upload and update user's profile banner/cover image.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadBanner(Request $request): JsonResponse
    {
        return $this->uploadImage($request, 'cover');
    }

    /**
     * Update current user's profile information.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        // Authentication handled by jwt.auth middleware
        $user = JWTAuth::user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|min:3|max:30|regex:/^[a-zA-Z0-9_]+$/|unique:users,username,' . $user->id,
            'bio' => 'sometimes|nullable|string|max:500',
            'location' => 'sometimes|nullable|string|max:100',
            'website' => 'sometimes|nullable|url|max:255',
        ]);

        try {
            $updateData = $request->only(['name', 'username', 'bio', 'location', 'website']);

            // Handle profile access pricing (optional)
            if ($request->has('profile_access_price')) {
                $updateData['profile_access_price'] = (float) $request->input('profile_access_price');
            }
            if ($request->has('profile_access_price_3_months')) {
                $updateData['profile_access_price_3_months'] = (float) $request->input('profile_access_price_3_months');
            }
            if ($request->has('profile_access_price_6_months')) {
                $updateData['profile_access_price_6_months'] = (float) $request->input('profile_access_price_6_months');
            }
            if ($request->has('profile_access_price_12_months')) {
                $updateData['profile_access_price_12_months'] = (float) $request->input('profile_access_price_12_months');
            }

            $user->update($updateData);
            $user->refresh();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $this->buildUserData($user),
                ],
                'message' => __('Profile updated successfully.'),
            ]);
        } catch (\Exception $e) {
            Log::error('Profile update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('Failed to update profile.'),
                'errors' => ['general' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Check follow status for the profile user.
     * Returns whether the authenticated user is following the profile owner.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkFollowStatus(Request $request): JsonResponse
    {
        if (!$this->user) {
            return response()->json([
                'success' => false,
                'message' => __('Profile not found.'),
            ], 404);
        }

        $authUser = JWTAuth::user();
        if (!$authUser) {
            return response()->json([
                'success' => true,
                'data' => [
                    'is_following' => false,
                    'is_subscribed' => false,
                ],
            ]);
        }

        $isFollowing = ListsHelperServiceProvider::isUserFollowing($authUser->id, $this->user->id);
        $isSubscribed = PostsHelperServiceProvider::hasActiveSub($authUser->id, $this->user->id);

        return response()->json([
            'success' => true,
            'data' => [
                'is_following' => $isFollowing,
                'is_subscribed' => $isSubscribed,
                'action_text' => $isFollowing ? __('Unfollow') : __('Follow'),
            ],
        ]);
    }

    /**
     * Toggle follow status for the profile user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function toggleFollow(Request $request): JsonResponse
    {
        if (!$this->user) {
            return response()->json([
                'success' => false,
                'message' => __('Profile not found.'),
            ], 404);
        }

        $authUser = JWTAuth::user();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => __('Authentication required.'),
            ], 401);
        }

        // Cannot follow yourself
        if ($authUser->id === $this->user->id) {
            return response()->json([
                'success' => false,
                'message' => __('You cannot follow yourself.'),
            ], 400);
        }

        try {
            ListsHelperServiceProvider::managePredefinedUserMemberList(
                $authUser->id,
                $this->user->id,
                ListsHelperServiceProvider::getUserFollowingType($this->user->id)
            );

            // Send notification to the profile owner
            $isFollowing = ListsHelperServiceProvider::isUserFollowing($authUser->id, $this->user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_following' => $isFollowing,
                    'action_text' => $isFollowing ? __('Unfollow') : __('Follow'),
                    'message' => $isFollowing ? __('You are now following this user.') : __('You have unfollowed this user.'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Toggle follow error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('Failed to update follow status.'),
            ], 500);
        }
    }

    /**
     * Show single post detail page (Inertia).
     */
    public function showPost(Request $request, $postId)
    {
        $post = Post::with([
            'user', 'reactions', 'attachments', 'bookmarks', 'postPurchases',
        ])->withCount('comments')->find($postId);

        if (!$post) {
            return $this->inertiaError($request, 404, __('Post not found.'));
        }

        // Load initial comments (top-level only, newest first)
        $comments = $post->comments()
            ->with(['author'])
            ->where('is_deleted', false)
            ->orderBy('created_at', 'asc')
            ->paginate(3);

        $commentsData = $comments->map(function ($comment) {
            $currentUserId = Auth::id();
            return [
                'id' => (string) $comment->id,
                'postId' => (string) $comment->post_id,
                'author' => [
                    'id' => (string) $comment->author->id,
                    'username' => $comment->author->username,
                    'name' => $comment->author->name,
                    'avatar' => $comment->author->avatar
                        ? GenericHelperServiceProvider::getStorageAvatarPath($comment->author->avatar)
                        : null,
                    'isVerified' => (bool) ($comment->author->verification?->status === 'verified'),
                ],
                'content' => $comment->message,
                'createdAt' => $comment->created_at?->toIso8601String(),
                'updatedAt' => $comment->updated_at?->toIso8601String(),
                'likesCount' => $comment->reactions()->count(),
                'userActions' => $currentUserId ? [
                    'liked' => $comment->reactions()->where('user_id', $currentUserId)->exists(),
                ] : null,
            ];
        })->toArray();

        $authUser = Auth::user();

        return Inertia::render('SinglePost', [
            'post' => PostResource::format($post, $post->user),
            'comments' => $commentsData,
            'comments_pagination' => [
                'current_page' => $comments->currentPage(),
                'has_more_pages' => $comments->hasMorePages(),
                'total' => $comments->total(),
            ],
            'is_owner' => $authUser && $authUser->id === $post->user_id,
            'has_sub' => $authUser ? PostsHelperServiceProvider::hasActiveSub($authUser->id, $post->user_id) : false,
            'auth_user' => $authUser ? [
                'id' => $authUser->id,
                'username' => $authUser->username,
                'name' => $authUser->name,
                'avatar' => $authUser->avatar
                    ? GenericHelperServiceProvider::getStorageAvatarPath($authUser->avatar)
                    : null,
            ] : null,
        ]);
    }

    // ─── Private Helpers ─────────────────────────────────────────────

    /**
     * Build media type counts for a user's posts.
     * Counts ignore current filter/search so pills always show accurate totals.
     * Applies the same access-control filters as ProfilePostsQuery (approved, not expired).
     */
    private function buildPostCounts(int $userId): array
    {
        $baseQuery = Post::where('user_id', $userId)
            ->where('status', \App\Model\Post::APPROVED_STATUS)
            ->notExpiredAndReleased();

        $imageTypes = AttachmentServiceProvider::getTypeByExtension('image');
        $videoTypes = AttachmentServiceProvider::getTypeByExtension('video');

        $allCount = (clone $baseQuery)->whereHas('attachments')->count();
        $photoCount = (clone $baseQuery)
            ->whereHas('attachments', fn ($q) => $q->whereIn('type', $imageTypes))
            ->count();
        $videoCount = (clone $baseQuery)
            ->whereHas('attachments', fn ($q) => $q->whereIn('type', $videoTypes))
            ->count();

        return [
            'all' => $allCount,
            'photo' => $photoCount,
            'video' => $videoCount,
        ];
    }

    /**
     * Render an Inertia error page with the given status code.
     */
    private function inertiaError(Request $request, int $status, string $message)
    {
        return Inertia::render('Error', [
            'status' => $status,
            'message' => $message,
        ])->toResponse($request)->setStatusCode($status);
    }

    /**
     * Build the standard user data array for API/Inertia responses.
     */
    private function buildUserData(?User $user = null): array
    {
        $u = $user ?? $this->user;

        // Verified = email verified + birthdate set + ID verification approved (matches legacy blade logic)
        $isVerified = $u->email_verified_at
            && $u->birthdate
            && $u->verification
            && $u->verification->status === 'verified';

        $isAmbassador = $isVerified && $u->userAffiliation?->name === 'ambassador';

        return [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'avatar' => $u->avatar,
            'cover' => $u->cover,
            'bio' => '',
            'location' => $u->location,
            'website' => !getSetting('profiles.disable_website_link_on_profile') ? $u->website : null,
            'gender_pronoun' => getSetting('profiles.allow_gender_pronouns') ? $u->gender_pronoun : null,
            'created_at' => $u->created_at?->toDateTimeString(),
            'is_verified' => (bool) $isVerified,
            'is_ambassador' => $isAmbassador,
            'is_professional' => $u->is_professional ?? false,
            'paid_profile' => $u->paid_profile ?? false,
            'open_profile' => (bool) ($u->open_profile ?? false),
            'user_role' => $u->userRole?->name ?? 'fan',
            'profile_access_price' => $u->profile_access_price,
            'profile_access_price_3_months' => $u->profile_access_price_3_months,
            'profile_access_price_6_months' => $u->profile_access_price_6_months,
            'profile_access_price_12_months' => $u->profile_access_price_12_months,
            'has_shop_items' => $u->shopItems()->count() > 0,
            'stats' => [
                'posts_count' => PostsHelperServiceProvider::numberPublicPostUser($u->id),
                'media_count' => PostsHelperServiceProvider::numberMediaForUser($u->id),
                'followers_count' => $u->followers_count ?? 0,
                'following_count' => $u->following_count ?? 0,
            ],
        ];
    }

    /**
     * Build relationship data for the current viewer and profile user.
     */
    private function buildRelationship(): ?array
    {
        if (!Auth::check() || Auth::id() === $this->user->id) {
            return null;
        }

        $viewer = Auth::user();
        $followingList = $viewer->lists()->where('name', 'Following')->where('type', 'following')->first();
        $isFollowing = $followingList && $followingList->members()->where('user_id', $this->user->id)->exists();
        $followersList = $this->user->lists()->where('name', 'Following')->where('type', 'following')->first();
        $isFollowedBy = $followersList && $followersList->members()->where('user_id', $viewer->id)->exists();

        $blockedList = $viewer->lists()->where('type', 'blocked')->first();
        $isBlocked = $blockedList && $blockedList->members()->where('user_id', $this->user->id)->exists();
        $hasBlocked = $this->user->lists()->where('type', 'blocked')->first()?->members()->where('user_id', $viewer->id)->exists() ?? false;

        return [
            'following' => $isFollowing,
            'followedBy' => $isFollowedBy,
            'canMessage' => $isFollowing || $viewer->role_id !== 5,
            'isBlocked' => $isBlocked,
            'hasBlocked' => $hasBlocked,
        ];
    }

    /**
     * Map posts collection to frontend-ready array.
     */
    private function buildPostsData($posts): array
    {
        $currentUserId = Auth::id();

        return $posts->map(function ($post) use ($currentUserId) {
            return [
                'id' => $post->id,
                'user_id' => $post->user_id,
                'text' => $post->text,
                'price' => $post->price,
                'created_at' => $post->created_at?->toDateTimeString(),
                'attachments' => $post->attachments?->map(function ($attachment) {
                    return AttachmentHelper::format($attachment);
                })->toArray() ?? [],
                'reactions_count' => $post->relationLoaded('reactions') ? $post->reactions->count() : 0,
                'comments_count' => $post->comments_count ?? 0,
                'is_liked' => $currentUserId && $post->relationLoaded('reactions')
                    ? $post->reactions->contains('user_id', $currentUserId)
                    : false,
                'is_bookmarked' => $currentUserId && $post->relationLoaded('bookmarks')
                    ? $post->bookmarks->contains('user_id', $currentUserId)
                    : false,
            ];
        })->toArray();
    }

    /**
     * Build paginator config for Inertia, rewriting URLs with the given path segment.
     */
    private function buildPaginatorConfig($paginator, string $segment): array
    {
        return [
            'next_page_url' => str_replace(['?page=', '?filter='], ["/{$segment}?page=", "/{$segment}?filter="], $paginator->nextPageUrl()),
            'prev_page_url' => str_replace(['?page=', '?filter='], ["/{$segment}?page=", "/{$segment}?filter="], $paginator->previousPageUrl()),
            'current_page' => $paginator->currentPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    /**
     * Build a truncated SEO description from the user's bio.
     */
    private function buildSeoDescription(): ?string
    {
        return GenericHelperServiceProvider::parseProfileMarkdownBio($this->user->bio);
    }

    /**
     * Get recent media attachments if the viewer has access.
     */
    private function getRecentMedia(): mixed
    {
        if ($this->hasSub || (Auth::check() && Auth::id() == $this->user->id) || (getSetting('profiles.allow_users_enabling_open_profiles') && $this->user->open_profile)) {
            $service = new PostQueryService();
            $params = PostQueryParams::make()
                ->forUser($this->user->id)
                ->viewedBy(Auth::user())
                ->withMediaType('media')
                ->withSortBy('latest')
                ->withSortOrder('desc')
                ->page(1);

            $posts = $service->profilePosts($params);

            return PostResource::collection($posts);
        }

        return false;
    }

    /**
     * Handle image upload for both avatar and cover.
     */
    private function uploadImage(Request $request, string $type): JsonResponse
    {
        $mimes = $type === 'avatar' ? 'jpg,jpeg,png,webp,gif' : 'jpg,jpeg,png,webp';
        $maxSize = getSetting('media.max_avatar_cover_file_size')
            ? ((int) getSetting('media.max_avatar_cover_file_size') * 1000)
            : 5120;

        $request->validate([
            'file' => "required|image|mimes:{$mimes}|max:{$maxSize}",
        ]);

        try {
            $user = JWTAuth::user();
            $file = $request->file('file');
            $directory = $type === 'avatar' ? 'users/avatar' : 'users/cover';
            $disk = Storage::disk('s3');
            $fileId = Uuid::uuid4()->getHex();
            $extension = $file->guessClientExtension();
            $filePath = $directory . '/' . $fileId . '.' . $extension;

            // Get configured size
            $settingKey = $type === 'avatar' ? 'media.users_avatars_size' : 'media.users_covers_size';
            $defaultWidth = $type === 'avatar' ? 96 : 599;
            $defaultHeight = $type === 'avatar' ? 96 : 180;
            $width = $defaultWidth;
            $height = $defaultHeight;

            if (getSetting($settingKey)) {
                $sizes = explode('x', getSetting($settingKey));
                if (isset($sizes[0])) {
                    $width = (int) $sizes[0];
                }
                if (isset($sizes[1])) {
                    $height = (int) $sizes[1];
                }
            }

            // Process and resize image
            $img = Image::make($file);
            $img->fit($width, $height)->orientate();
            $img->encode('jpg', 100);

            // Save to S3
            $disk->put($filePath, $img, 'public');

            // Update user
            $field = $type === 'avatar' ? 'avatar' : 'cover';
            $user->update([$field => $filePath]);

            // Generate URL
            $url = $type === 'avatar'
                ? GenericHelperServiceProvider::getStorageAvatarPath($filePath)
                : GenericHelperServiceProvider::getStorageCoverPath($filePath);

            $data = ['url' => $url];
            if ($type === 'avatar') {
                $data['thumbnail'] = $url;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error(ucfirst($type) . ' upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('Failed to upload ' . $type . '.'),
                'errors' => ['file' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Checks if current logged user (if any) has rights to view the profile media.
     */
    protected function setAccessRules(): void
    {
        $viewerUser = null;
        if (Auth::check()) {
            $viewerUser = Auth::user();
        }
        if ($viewerUser) {
            $this->hasSub = PostsHelperServiceProvider::hasActiveSub($viewerUser->id, $this->user->id);
            $this->isFollowing = ListsHelperServiceProvider::loggedUserIsFollowingUser($this->user->id);
            if ($viewerUser->id === $this->user->id) {
                $this->hasSub = true;
                $this->isOwner = true;
                $this->viewerHasChatAccess = true;
            }
            if (!$this->user->paid_profile && $this->isFollowing) {
                $this->hasSub = true;
                $this->viewerHasChatAccess = true;
            }
            if ((getSetting('profiles.allow_users_enabling_open_profiles') && $this->user->open_profile) && $this->isFollowing) {
                $this->hasSub = true;
                $this->viewerHasChatAccess = true;
            }
            if ($viewerUser->role_id === 1) {
                $this->hasSub = true;
            }
        }
    }

    /**
     * Calculate offer/discount data for API responses (snake_case).
     */
    protected function calculateOfferForApi(): array
    {
        if (!$this->user->offer && !$this->user->paid_profile) {
            return [];
        }

        $offer = [];

        if ($this->user->offer && !getSetting('profiles.disable_profile_offers')) {
            $discount30 = 100 - (($this->user->profile_access_price * 100) / ($this->user->offer->old_profile_access_price > 0 ? $this->user->offer->old_profile_access_price : 1));
            $discount90 = 100 - (($this->user->profile_access_price_3_months * 100) / ($this->user->offer->old_profile_access_price_3_months ? $this->user->offer->old_profile_access_price_3_months : 1));
            $discount182 = 100 - (($this->user->profile_access_price_6_months * 100) / ($this->user->offer->old_profile_access_price_6_months ? $this->user->offer->old_profile_access_price_6_months : 1));
            $discount365 = 100 - (($this->user->profile_access_price_12_months * 100) / ($this->user->offer->old_profile_access_price_12_months ? $this->user->offer->old_profile_access_price_12_months : 1));
            $expiringDate = $this->user->offer->expires_at;
            $currentDate = Carbon::now();
            if ($expiringDate > $currentDate) {
                $offer = [
                    'discount_amount' => [
                        '30' => $discount30,
                        '90' => $discount90,
                        '182' => $discount182,
                        '365' => $discount365,
                    ],
                    'days_remaining' => $expiringDate->diffInDays($currentDate),
                    'expires_at' => $expiringDate->toDateTimeString(),
                ];
            }
        } elseif ($this->user->paid_profile) {
            $discount90 = 100 - ((($this->user->profile_access_price_3_months / 3) / ($this->user->profile_access_price > 0 ? $this->user->profile_access_price : 1)) * 100);
            $discount182 = 100 - ((($this->user->profile_access_price_6_months / 6) / ($this->user->profile_access_price > 0 ? $this->user->profile_access_price : 1)) * 100);
            $discount365 = 100 - ((($this->user->profile_access_price_12_months / 12) / ($this->user->profile_access_price > 0 ? $this->user->profile_access_price : 1)) * 100);
            $offer = [
                'discount_amount' => [
                    '30' => 0,
                    '90' => $discount90,
                    '182' => $discount182,
                    '365' => $discount365,
                ],
            ];
        }

        return $offer;
    }

    /**
     * Check if user's geolocation is blocked.
     */
    protected function isGeoLocationBlocked(): bool
    {
        if (Auth::check() && Auth::user()->role_id === 1) {
            return false;
        }
        if (getSetting('security.allow_geo_blocking')) {
            if ($this->user->enable_geoblocking) {
                if (isset($this->user->settings['geoblocked_countries'])) {
                    try {
                        $countries = json_decode($this->user->settings['geoblocked_countries']);
                        $blockedCountries = Country::whereIn('name', $countries)->get();

                        // Check if API key is configured
                        $apiKey = getSetting('security.abstract_api_key');
                        if (empty($apiKey)) {
                            Log::warning('Geolocation API key is not configured. Geoblocking disabled.');

                            return false;
                        }

                        $client = new \GuzzleHttp\Client();
                        $apiRequest = $client->get('https://ipgeolocation.abstractapi.com/v1/?api_key=' . $apiKey . '&ip_address=' . $_SERVER['REMOTE_ADDR']);
                        $apiData = json_decode($apiRequest->getBody()->getContents());

                        foreach ($blockedCountries as $country) {
                            if (isset($apiData->country_code) && $country->country_code == $apiData->country_code) {
                                if (!(Auth::check() && Auth::user()->id === $this->user->id)) {
                                    return true;
                                }
                            }
                        }
                    } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                        Log::error('Geolocation API error: ' . $e->getMessage());

                        return false;
                    } catch (\Exception $e) {
                        Log::error('Geolocation check error: ' . $e->getMessage());

                        return false;
                    }
                }
            }
        }

        return false;
    }
}
