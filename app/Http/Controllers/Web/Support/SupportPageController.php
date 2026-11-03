<?php

namespace App\Http\Controllers\Web\Support;

use App\Exceptions\SupportRequestException;
use App\Http\Controllers\Web\WebController;
use App\Http\Requests\Web\Support\DeliveryPartnerRequest;
use App\Http\Requests\Web\Support\DevelopmentEnquiryRequest;
use App\Http\Requests\Web\Support\VendorRegistrationRequest;
use App\Services\CategoryService;
use App\Services\SupportRequestService;
use App\Support\SupportForms;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

/**
 * The enquiry forms (delivery partner, vendor, development request) and the help page with its callback request. Each form is an ordinary
 * server-validated post: errors come back under the fields with what was typed, and a successful one shows a thank-you with a reference.
 * What is saved is the admin's queue row (SupportRequestService), the same as the app's API.
 */
class SupportPageController extends WebController
{
    public const SESSION_DONE = 'support.done';

    public function __construct(
        private readonly SupportRequestService $requests,
        private readonly CategoryService $categories,
    ) {}

    public function form(Request $request, string $key): View
    {
        $form = SupportForms::get($key, $key === 'vendor' ? $this->categoryNames() : []);
        $user = $request->user();

        return $this->page('support.form', [
            'key' => $key, 'form' => $form, 'title' => $form['title'], 'page' => $key,
            'bag' => $this->bagOf($request, $key), 'done' => session(self::SESSION_DONE),
            'defaults' => $user ? ['name' => $user->name, 'owner' => $user->name, 'phone' => $user->mobile, 'email' => $user->email] : [],
        ]);
    }

    public function storeDelivery(DeliveryPartnerRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $row = $this->requests->joinAsDeliveryPartner([
            'name' => $v['name'], 'mobile' => $v['phone'], 'address' => $v['city'], 'pincode' => $v['pincode'],
            'description' => "Vehicle: {$v['vehicle']}. Licence: {$v['licence']}. Availability: {$v['shift']}.",
        ]);

        return $this->done('delivery', $row['id']);
    }

    public function storeVendor(VendorRegistrationRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $row = $this->requests->registerVendor($request->user(), [
            'name' => $v['owner'], 'mobile' => $v['phone'], 'email' => $v['email'], 'business_type' => $v['business_type'],
            'description' => collect(["Business: {$v['business']}", "Category: {$v['category']}", ! empty($v['gstin']) ? "GSTIN: {$v['gstin']}" : null, "Location: {$v['city']} {$v['pincode']}", ! empty($v['about']) ? "About: {$v['about']}" : null])->filter()->implode('. '),
        ]);

        return $this->done('vendor', $row['id']);
    }

    public function storeDevelopment(DevelopmentEnquiryRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $row = $this->requests->requestDevelopment($request->user(), [
            'name' => $v['name'], 'mobile' => $v['phone'], 'email' => $v['email'],
            'request_type' => $v['kind'] === 'Create Website' ? 'web_development' : 'app_development',
            'description' => ($v['kind'] === 'Both' ? '[Needs both an app and a website] ' : '').$v['details']."\nBudget: {$v['budget']}\nTimeline: {$v['timeline']}",
        ]);

        return $this->done('devrequest', $row['id']);
    }

    public function help(Request $request): View
    {
        return $this->page('support.help', ['faqs' => config('faq.items'), 'done' => session(self::SESSION_DONE), 'user' => $request->user()]);
    }

    /** POST /support/callback - the customer's own name and number are used; one open callback at a time */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $row = $this->requests->requestCallback($request->user());
        } catch (SupportRequestException $e) {
            return $this->redirectWith('help', 'error', Lang::has($e->getMessage()) ? __($e->getMessage()) : $e->getMessage());
        }

        return $this->done('help', $row['id'], 'Callback requested! We\'ll call you on your registered number.');
    }

    private function done(string $key, int $id, ?string $message = null): RedirectResponse
    {
        $message ??= SupportForms::get($key)['success'];
        session()->flash(self::SESSION_DONE, ['ref' => '#'.$id, 'message' => $message]);

        return redirect()->route($key === 'help' ? 'help' : $key);
    }

    /** The errors of this form (they are kept under the form's own name). */
    private function bagOf(Request $request, string $key): \Illuminate\Support\MessageBag
    {
        return $request->session()->get('errors')?->getBag($key) ?? new \Illuminate\Support\MessageBag;
    }

    /** @return list<string> */
    private function categoryNames(): array
    {
        try {
            return collect($this->categories->tree('standard_delivery'))->pluck('name')->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
