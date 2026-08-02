<?php

namespace App\Modules\Admin\Users\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Access\Services\AccessControlService;
use App\Modules\Admin\Users\Http\Requests\StoreUserRequest;
use App\Modules\Admin\Users\Http\Requests\UpdateUserRequest;
use App\Modules\Admin\Users\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsersController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly AccessControlService $accessControlService,
    ) {}

    /**
     * Display the users listing.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'account_type' => ['nullable', 'in:customer,admin'],
            'role' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $actor = $request->user();

        return Inertia::render('admin/users/pages/Index', [
            'users' => $this->userService->paginateForAdmin($actor, $filters),
            'filters' => $filters,
            'roles' => $this->accessControlService->availableRoleOptionsFor($actor),
        ]);
    }

    /**
     * Display the create-user page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('admin/users/pages/Create', [
            'roles' => $this->accessControlService->availableRoleOptionsFor($request->user()),
            'permissionGroups' => $this->accessControlService->permissionGroupsFor($request->user()),
            'rolePermissions' => $this->accessControlService->assignableRolePermissionMapFor($request->user()),
        ]);
    }

    /**
     * Store a newly created administrative user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->userService->create($request->user(), $request->validated());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Administrative user created successfully.');
    }

    /**
     * Display the specified user profile.
     */
    public function show(User $user): Response
    {
        $actor = request()->user();

        $this->accessControlService->assertCanManageUser($actor, $user);

        return Inertia::render('admin/users/pages/Show', [
            'user' => $this->userService->detailPayload($user, $actor),
        ]);
    }

    /**
     * Display the specified user edit page.
     */
    public function edit(User $user): Response
    {
        $actor = request()->user();

        $this->accessControlService->assertCanManageUser($actor, $user);

        return Inertia::render('admin/users/pages/Edit', [
            'user' => $this->userService->editPayload($user),
            'roles' => $this->accessControlService->availableRoleOptionsFor($actor),
            'permissionGroups' => $this->accessControlService->permissionGroupsFor($actor),
            'rolePermissions' => $this->accessControlService->assignableRolePermissionMapFor($actor),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($request->user(), $user, $request->validated());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    /**
     * Toggle the specified user's active status.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        $result = $this->userService->toggleStatus(request()->user(), $user);

        return back()->with($result['updated'] ? 'success' : 'error', $result['message']);
    }
}
