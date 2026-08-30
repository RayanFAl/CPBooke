<?php

namespace App\Modules\Admin\Users\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Access\Services\AccessControlService;
use App\Modules\Admin\Users\Http\Requests\StoreUserRequest;
use App\Modules\Admin\Users\Http\Requests\UpdateCustomerIdentityRequest;
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
     * Display the customers directory.
     */
    public function index(Request $request): Response
    {
        return $this->listing($request, User::ACCOUNT_TYPE_CUSTOMER);
    }

    /**
     * Display the Control Panel team directory.
     */
    public function teamIndex(Request $request): Response
    {
        return $this->listing($request, User::ACCOUNT_TYPE_ADMIN);
    }

    /**
     * Keep the legacy users directory URL pointed at customers.
     */
    public function legacyIndex(): RedirectResponse
    {
        return redirect()->route('admin.customers.index');
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
            ->route('admin.team.show', $user)
            ->with('success', 'Administrative user created successfully.');
    }

    /**
     * Display the specified user profile.
     */
    public function show(Request $request, User $user): Response|RedirectResponse
    {
        if ($request->routeIs('admin.customers.show') && ! $user->isCustomerAccount()) {
            return redirect()->route('admin.team.show', $user);
        }

        if ($request->routeIs('admin.team.show') && ! $user->isAdminAccount()) {
            return redirect()->route('admin.customers.show', $user);
        }

        $actor = $request->user();

        $this->accessControlService->assertCanManageUser($actor, $user);

        return Inertia::render('admin/users/pages/Show', [
            'user' => $this->userService->detailPayload($user, $actor),
        ]);
    }

    /**
     * Display the customer identity editor.
     */
    public function editCustomer(User $user): Response|RedirectResponse
    {
        if (! $user->isCustomerAccount()) {
            return redirect()->route('admin.users.edit', $user);
        }

        $actor = request()->user();
        $this->accessControlService->assertCanManageUser($actor, $user);

        return Inertia::render('admin/users/pages/EditCustomer', [
            'user' => $this->userService->editPayload($user),
        ]);
    }

    /**
     * Display the specified user edit page.
     */
    public function edit(User $user): Response
    {
        abort_unless($user->isAdminAccount(), 404);

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
     * Update customer identity from CRM.
     */
    public function updateCustomer(UpdateCustomerIdentityRequest $request, User $user): RedirectResponse
    {
        if (! $user->isCustomerAccount()) {
            abort(404);
        }

        $this->userService->updateIdentity($request->user(), $user, $request->validated());

        return redirect()
            ->route('admin.customers.show', $user)
            ->with('success', 'Customer details updated successfully.');
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->isAdminAccount(), 404);

        $this->userService->update($request->user(), $user, $request->validated());

        return redirect()
            ->route('admin.team.show', $user)
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

    /**
     * Permanently delete a Control Panel team member.
     */
    public function destroy(User $user): RedirectResponse
    {
        abort_unless($user->isAdminAccount(), 404);

        $result = $this->userService->deleteTeamMember(request()->user(), $user);

        if (! $result['deleted']) {
            return back()->with('error', $result['message']);
        }

        return redirect()
            ->route('admin.team.index')
            ->with('success', $result['message']);
    }

    /**
     * Render a directory listing locked to one account type.
     */
    private function listing(Request $request, string $accountType): Response
    {
        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'in:active,inactive'],
        ];

        if ($accountType === User::ACCOUNT_TYPE_ADMIN) {
            $rules['role'] = ['nullable', 'string', 'max:50'];
        }

        $filters = $request->validate($rules);
        $filters['account_type'] = $accountType;

        $actor = $request->user();

        return Inertia::render('admin/users/pages/Index', [
            'users' => $this->userService->paginateForAdmin($actor, $filters),
            'filters' => $filters,
            'roles' => $this->accessControlService->availableRoleOptionsFor($actor),
            'audience' => $accountType === User::ACCOUNT_TYPE_CUSTOMER ? 'customer' : 'team',
        ]);
    }
}
