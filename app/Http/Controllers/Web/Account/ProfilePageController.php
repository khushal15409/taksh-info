<?php

namespace App\Http\Controllers\Web\Account;

use App\Exceptions\AddressProblemException;
use App\Http\Controllers\Web\WebController;
use App\Http\Requests\Web\Account\UpdateProfilePageRequest;
use App\Http\Requests\Web\Orders\OrderFilters;
use App\Services\AddressService;
use App\Services\Orders\OrderPageService;
use App\Services\Returns\ReturnService;
use App\Services\ProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The account hub: profile details, saved addresses, orders and returns as tabs (?tab=). Profile and addresses are rendered here;
 * the order and return lists are still drawn by their own module scripts until those modules are moved (see the orders phase).
 */
class ProfilePageController extends WebController
{
    public const TABS = ['profile', 'addresses', 'orders', 'returns'];

    public function __construct(
        private readonly ProfileService $profile,
        private readonly AddressService $addresses,
        private readonly OrderPageService $orders,
        private readonly ReturnService $returns,
    ) {}

    public function show(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'profile';
        $user = $request->user();

        $filters = OrderFilters::from($request);

        return $this->page('account.profile', [
            'tab' => $tab,
            'user' => $user,
            'addressBook' => $user && $tab === 'addresses' ? $this->addresses->list($user->id) : null,
            'filters' => $filters,
            'orders' => $user && $tab === 'orders' ? $this->orders->list($user, $filters->toService())->withPath(route('profile'))->appends($filters->query()) : null,
            'returns' => $user && $tab === 'returns' ? $this->returnsPage($user, $request) : null,
        ]);
    }

    /** One page of the customer's returns (?page=), newest first. */
    private function returnsPage(\App\Models\User $user, Request $request): \Illuminate\Pagination\LengthAwarePaginator
    {
        $d = $this->returns->list($user, null);

        return (new \Illuminate\Pagination\LengthAwarePaginator($d['data'], $d['total'], $d['per_page'], $d['current_page'], ['path' => route('profile')]))->appends(['tab' => 'returns']);
    }

    public function update(UpdateProfilePageRequest $request): RedirectResponse
    {
        $this->profile->update($request->user(), $request->validated());

        return $this->redirectWith('profile', 'success', 'Profile Updated Successfully', ['tab' => 'profile']);
    }

    public function setDefaultAddress(Request $request, int $id): RedirectResponse
    {
        return $this->addressAction(fn () => $this->addresses->setDefault($request->user()->id, $id), 'Default address updated');
    }

    public function destroyAddress(Request $request, int $id): RedirectResponse
    {
        return $this->addressAction(fn () => $this->addresses->delete($request->user()->id, $id), 'Address removed');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $this->flash('success', 'Logged out');

        return redirect()->route('home');
    }

    /** Run an address change and come back to the list with the outcome. */
    private function addressAction(callable $change, string $done): RedirectResponse
    {
        try {
            $change();
        } catch (AddressProblemException $e) {
            return $this->redirectWith('profile', 'error', $this->message($e), ['tab' => 'addresses']);
        }

        return $this->redirectWith('profile', 'success', $done, ['tab' => 'addresses']);
    }

    private function message(\Throwable $e): string
    {
        return __($e->getMessage()) ?: $e->getMessage();
    }
}
