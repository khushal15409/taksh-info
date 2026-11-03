<?php

namespace App\Support;

/**
 * The enquiry forms the website offers (delivery partner, vendor, development request): their wording and fields. A field is
 * [name, label, type, options] with an optional 'required', 'hint', 'wide', 'maxlength' and 'value'. The rules that check them are in
 * App\Http\Requests\Web\Support\*; what each form sends to the admin's queues is in SupportPageController.
 */
final class SupportForms
{
    public const VEHICLES = ['Motorbike', 'Scooter', 'Electric scooter', 'Bicycle'];
    public const SHIFTS = ['Full time', 'Part time (evenings)', 'Weekends only'];
    public const KINDS = ['Create Application', 'Create Website', 'Both'];
    public const BUDGETS = ['Under ₹50,000', '₹50,000 - ₹2,00,000', '₹2,00,000 - ₹5,00,000', 'Above ₹5,00,000'];
    public const TIMELINES = ['Within 1 month', '1-3 months', '3-6 months', 'Flexible'];
    public const BUSINESS_TYPES = ['product_vendor' => 'Products', 'service_vendor' => 'Services'];

    /** @param list<string> $categories names for the vendor's "main category" (the catalogue's categories when there are any) */
    public static function get(string $key, array $categories = []): ?array
    {
        $f = fn (string $name, string $label, string $type = 'text', array $extra = []) => ['name' => $name, 'label' => $label, 'type' => $type] + $extra;
        $phone = ['inputmode' => 'tel'];

        return [
            'delivery' => [
                'title' => 'Join as Delivery Partner', 'emoji' => '🛵', 'tint' => 'from-emerald-700 via-emerald-600 to-lime-500',
                'intro' => 'Earn on your own schedule delivering Taksh orders in your city. Weekly payouts, fuel support and insurance cover.',
                'perks' => [['wallet', 'Weekly payouts'], ['clock', 'Flexible hours'], ['shield-check', 'Accident cover']],
                'route' => 'support.delivery.store', 'submit' => 'Submit Application',
                'success' => 'Application received! Our onboarding team will call you within 2 working days.',
                'fields' => [
                    $f('name', 'Full name', 'text', ['autofill' => 'name']), $f('phone', 'Mobile number', 'tel', ['autofill' => 'mobile'] + $phone),
                    $f('city', 'City', 'text', ['value' => 'Ahmedabad']), $f('pincode', 'Pincode', 'text', ['maxlength' => 6, 'inputmode' => 'numeric']),
                    $f('vehicle', 'Vehicle', 'select', ['options' => self::VEHICLES]), $f('licence', 'Driving licence number', 'text', ['hint' => 'Not needed for bicycles']),
                    $f('shift', 'Availability', 'select', ['options' => self::SHIFTS]),
                    $f('agree', 'I confirm the details are correct and agree to a background verification.', 'checkbox', ['wide' => true]),
                ],
            ],
            'vendor' => [
                'title' => 'Become a Vendor', 'emoji' => '🏪', 'tint' => 'from-purple-700 via-purple-600 to-fuchsia-500',
                'intro' => 'Sell your products to thousands of Taksh customers. List items, manage orders and get paid directly to your bank.',
                'perks' => [['store', 'Free store setup'], ['trending-up', 'More customers'], ['landmark', 'Direct payouts']],
                'route' => 'support.vendor.store', 'submit' => 'Register as Vendor',
                'success' => 'Vendor request submitted! Our partner team will verify your details and reach out shortly.',
                'fields' => [
                    $f('business', 'Business / shop name'), $f('owner', 'Owner name', 'text', ['autofill' => 'name']),
                    $f('phone', 'Mobile number', 'tel', ['autofill' => 'mobile'] + $phone), $f('email', 'Email', 'email', ['autofill' => 'email']),
                    $f('business_type', 'What will you sell?', 'select', ['options' => self::BUSINESS_TYPES]),
                    $f('category', 'Main category', 'select', ['options' => $categories ?: ['Groceries', 'Fresh produce', 'Snacks & sweets', 'Electronics', 'Home & decor', 'Other']]),
                    $f('gstin', 'GSTIN (optional)', 'text', ['maxlength' => 15, 'optional' => true]),
                    $f('city', 'City', 'text', ['value' => 'Ahmedabad']), $f('pincode', 'Pincode', 'text', ['maxlength' => 6, 'inputmode' => 'numeric']),
                    $f('about', 'Tell us about your products', 'textarea', ['wide' => true, 'optional' => true]),
                    $f('agree', 'I agree to the Taksh vendor terms.', 'checkbox', ['wide' => true]),
                ],
            ],
            'devrequest' => [
                'title' => 'Development Request', 'emoji' => '💻', 'tint' => 'from-sky-700 via-sky-600 to-cyan-500',
                'intro' => 'Need a mobile app or website for your business? Share your idea and the Taksh tech team will get back with a plan and quote.',
                'perks' => [['smartphone', 'Mobile apps'], ['globe', 'Websites'], ['headphones', 'Ongoing support']],
                'route' => 'support.development.store', 'submit' => 'Send Request',
                'success' => 'Request sent! Our tech team will contact you within 2 working days.',
                'fields' => [
                    $f('name', 'Your name', 'text', ['autofill' => 'name']), $f('phone', 'Mobile number', 'tel', ['autofill' => 'mobile'] + $phone), $f('email', 'Email', 'email', ['autofill' => 'email']),
                    $f('kind', 'What do you need?', 'select', ['options' => self::KINDS]), $f('budget', 'Budget (approx.)', 'select', ['options' => self::BUDGETS]),
                    $f('timeline', 'Timeline', 'select', ['options' => self::TIMELINES]), $f('details', 'Describe your requirement', 'textarea', ['wide' => true]),
                ],
            ],
        ][$key] ?? null;
    }
}
