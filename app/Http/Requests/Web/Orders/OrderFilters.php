<?php

namespace App\Http\Requests\Web\Orders;

use Illuminate\Http\Request;

/** The order list's address bar: ?status= &mode= &from= &to= &page= - anything unusable is ignored, never an error page. */
final class OrderFilters
{
    public const STATUSES = ['pending', 'confirmed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'];

    public function __construct(
        public readonly string $status = '',
        public readonly string $mode = '',
        public readonly string $from = '',
        public readonly string $to = '',
    ) {}

    public static function from(Request $request): self
    {
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false ? $v : '';
        $status = $request->query('status');
        $mode = $request->query('mode');

        return new self(
            in_array($status, self::STATUSES, true) ? $status : '',
            in_array($mode, ['standard', 'quick'], true) ? $mode : '',
            $date($request->query('from')),
            $date($request->query('to')),
        );
    }

    /** What OrderPageService::list() takes. */
    public function toService(): array
    {
        return ['order_status' => $this->status ?: null, 'delivery_mode' => $this->mode ?: null, 'from' => $this->from ?: null, 'to' => $this->to ?: null];
    }

    /** The query string that keeps these filters (and the tab) on a link. */
    public function query(): array
    {
        return array_filter(['tab' => 'orders', 'status' => $this->status, 'mode' => $this->mode, 'from' => $this->from, 'to' => $this->to]);
    }

    public function any(): bool
    {
        return $this->status !== '' || $this->mode !== '' || $this->from !== '' || $this->to !== '';
    }
}
