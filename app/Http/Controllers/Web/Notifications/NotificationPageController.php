<?php

namespace App\Http\Controllers\Web\Notifications;

use App\Exceptions\NotificationNotFoundException;
use App\Http\Controllers\Web\WebController;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The customer's notification inbox: the list (filter tabs, pages), one notification, and "mark all as read". */
class NotificationPageController extends WebController
{
    public const FILTERS = ['all', 'offers', 'orders', 'unread'];
    private const PER_PAGE = 15;

    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : 'all';
        $user = $request->user();

        return $this->page('notifications.index', [
            'filter' => $filter,
            'notifications' => $user ? $this->notifications->page($user, $filter, self::PER_PAGE)->withQueryString() : null,
            'unread' => $user ? $this->notifications->unreadCount($user) : 0,
        ]);
    }

    public function show(Request $request, int $id): View
    {
        try {
            $notification = $this->notifications->open($request->user(), $id);
        } catch (NotificationNotFoundException) {
            abort(404);
        }

        return $this->page('notifications.show', ['notification' => $notification]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->user());

        return $this->redirectWith('notifications', 'success', 'All notifications marked as read');
    }
}
