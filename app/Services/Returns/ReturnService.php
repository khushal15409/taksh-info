<?php

namespace App\Services\Returns;

use App\Enums\ReturnStatus;
use App\Exceptions\CartProblemException;
use App\Http\Resources\Order\ReturnResource;
use App\Models\User;
use App\Repositories\Contracts\ReturnRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/** Customer returns, as taksh-admin's CustomerReturnController runs them: a delivered line, inside its return window, only what is left of it. */
class ReturnService
{
    public function __construct(private readonly ReturnRepositoryInterface $returns, private readonly ReturnMediaStore $media) {}

    /**
     * @param  array{order_id: int, order_item_id: int, quantity: int, reason: string, resolution: string}  $data
     * @return array<string, mixed> the new return request
     */
    public function create(User $user, array $data): array
    {
        $return = $this->returns->transaction(function () use ($user, $data) {
            $order = $this->returns->ownedOrder($user->id, (int) $data['order_id']) ?? throw new CartProblemException('api.order_not_found', 404);

            if ($order->order_status !== 'delivered') {
                throw new CartProblemException('api.order_not_delivered', 400);
            }

            $line = $this->returns->orderItem($order, (int) $data['order_item_id']) ?? throw new CartProblemException('api.order_item_not_found', 404);

            // each product carries its own return window, counted from the day the order was delivered
            $window = (int) ($line->return_days ?? 0);
            if ($order->delivered_at && $window > 0 && now()->greaterThan($order->delivered_at->copy()->addDays($window))) {
                throw new CartProblemException('api.return_window_closed', 400);
            }

            // only what is left of the line can come back; pieces sent back on an earlier request are already gone
            if ((int) $data['quantity'] > (int) $line->qty - $this->returns->alreadyReturned($line->id)) {
                throw new CartProblemException('api.return_quantity_too_high', 400);
            }

            if ($this->returns->hasOpenReturn($line->id)) {
                throw new CartProblemException('api.return_already_exists', 400);
            }

            $product = $line->productVariant->product;

            $return = $this->returns->create([
                'order_id' => $order->id,
                'order_item_id' => $line->id,
                'product_id' => $product->id,
                'customer_id' => $user->id,
                'vendor_id' => $product->vendor_id,
                'reason' => $data['reason'],
                'resolution' => $data['resolution'],
                'status' => ReturnStatus::PENDING,
                'requested_at' => now(),
            ]);

            // what is actually coming back: without this the money side can only guess and would take the whole line back
            $this->returns->addItem($return, ['product_variant_id' => $line->product_variant_id, 'quantity' => (int) $data['quantity'], 'price' => $line->price]);
            $this->returns->addStatusLog($return, ['status' => ReturnStatus::PENDING->value, 'notes' => 'Return request created by customer', 'changed_by' => $user->id]);

            return $return;
        });

        return ReturnResource::make($this->returns->detail($user->id, $return->id) ?? $return)->resolve();
    }

    /**
     * Attach up to five pictures to the customer's own return request.
     *
     * @param  list<UploadedFile>  $files
     * @return list<array{id: int, file_url: ?string}>
     */
    public function uploadMedia(User $user, int $returnId, array $files): array
    {
        $return = $this->returns->ownedReturn($user->id, $returnId) ?? throw new CartProblemException('api.return_not_found', 404);

        try {
            $out = [];

            foreach ($files as $file) {
                $media = $this->returns->addMedia($return, [
                    'file_path' => $this->media->put($file), 'file_type' => 'image', 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(),
                ]);
                $out[] = ['id' => $media->id, 'file_url' => $media->file_url];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::error('Error uploading return media: '.$e->getMessage());

            throw new CartProblemException('api.media_upload_failed', 500);
        }
    }

    public function list(User $user, ?string $status): array
    {
        $page = $this->returns->paginate($user->id, $status, 15);

        return $page->through(fn ($r) => ReturnResource::make($r)->resolve())->toArray();
    }

    public function show(User $user, int $returnId): array
    {
        return ReturnResource::make($this->returns->detail($user->id, $returnId) ?? throw new CartProblemException('api.return_not_found', 404))->resolve();
    }

    /** @return array<string, mixed> */
    public function track(User $user, int $returnId): array
    {
        $return = $this->returns->detail($user->id, $returnId) ?? throw new CartProblemException('api.return_not_found', 404);
        $pickup = $this->returns->pickup($return->id);

        return [
            'return_id' => $return->id,
            'current_status' => $return->status->value,
            'current_status_label' => $return->status->label(),
            'quantity' => $return->returnedQty(),
            'pickup' => $pickup ? [
                'tracking_id' => $pickup->tracking_id,
                'status' => $pickup->status,
                'status_label' => ucwords(str_replace('_', ' ', $pickup->status)),
            ] : null,
            'status_history' => $this->returns->statusHistory($return)->map(fn ($log) => [
                'status' => $log->status, 'status_label' => ReturnStatus::tryFrom($log->status)?->label() ?? $log->status, 'notes' => $log->notes, 'created_at' => $log->created_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
