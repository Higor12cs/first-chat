<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantContext;
use App\Http\Requests\UserRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\ServiceQueueResource;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\ServiceQueue;
use App\Models\User;
use App\Services\Tenancy\TenantLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Users/Index', [
            'filters' => $request->only('search'),
            'users' => UserResource::collection(
                User::query()
                    ->with(['roles', 'serviceQueues', 'memberships'])
                    ->when($request->filled('search'), fn ($query) => $query->where(
                        fn ($inner) => $inner->where('name', 'ilike', "%{$request->string('search')}%")
                            ->orWhere('email', 'ilike', "%{$request->string('search')}%")
                    ))
                    ->orderBy('name')
                    ->paginate(20)
                    ->withQueryString()
            ),
            'roles' => RoleResource::collection(Role::query()->orderBy('name')->get()),
            'queues' => ServiceQueueResource::collection(ServiceQueue::query()->ordered()->get()),
        ]);
    }

    public function store(UserRequest $request, TenantLimits $limits): RedirectResponse
    {
        $limits->guardUserCreation();

        $user = $this->context->runWithoutTenant(fn (): User => User::create($request->accountAttributes()));

        $user->memberships()->create([
            'tenant_id' => $this->context->id(),
            ...$request->membershipAttributes(),
        ]);

        $user->roles()->sync($request->input('roles', []));
        $user->serviceQueues()->sync($request->input('service_queues', []));

        return back()->with('success', 'Usuário criado.');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->accountAttributes());

        $user->memberships()
            ->firstOrNew(['tenant_id' => $this->context->id()])
            ->fill($request->membershipAttributes())
            ->save();

        $user->roles()->sync($request->input('roles', []));
        $user->serviceQueues()->sync($request->input('service_queues', []));

        return back()->with('success', 'Usuário atualizado.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'Você não pode excluir o próprio usuário.');

        $user->roles()->detach();
        $user->serviceQueues()->detach();
        $user->memberships()->where('tenant_id', $this->context->id())->delete();

        if ($user->memberships()->count() === 0) {
            $this->context->runWithoutTenant(fn () => $user->delete());
        }

        return back()->with('success', 'Usuário removido da conta.');
    }
}
