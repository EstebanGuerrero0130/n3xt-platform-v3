<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Review;
use App\Services\ImageUploader;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * Reseñas: el cliente califica un pedido terminado (una por pedido), el taller
 * las aprueba y solo las aprobadas se ven en el sitio.
 */
class ReviewController extends Controller
{
    use ApiResponse;

    private const REVIEWABLE = ['completed', 'shipped'];

    /** Públicas aprobadas; ?product=ID filtra por producto del catálogo. */
    public function index(Request $request)
    {
        $query = Review::where('status', 'approved')->latest();
        if ($product = $request->query('product')) {
            $query->whereJsonContains('product_ids', (string) $product);
        }
        // Se muestran las 24 más nuevas, pero la calificación es de todas (va en el JSON-LD)
        $count = (clone $query)->reorder()->count();
        $average = $count ? round((float) (clone $query)->reorder()->avg('rating'), 1) : null;
        $reviews = $query->limit(24)->get();

        return $this->success([
            'reviews' => $reviews->map->toPublic(),
            'count' => $count,
            'average' => $average,
        ]);
    }

    public function store(Request $request, ImageUploader $uploader, int $id)
    {
        $customer = $request->user();
        if (!$customer->isVerified()) {
            return $this->error('Verifica tu correo para dejar reseñas.', 403);
        }
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'comment.min' => 'Cuéntanos un poco más (mínimo 10 caracteres).',
        ]);

        $order = Order::whereKey($id)->whereRaw('LOWER(customer_email) = ?', [strtolower($customer->email)])->first();
        if (!$order) {
            return $this->error('Pedido no encontrado.', 404);
        }
        if (!in_array($order->status, self::REVIEWABLE, true)) {
            return $this->error('Puedes calificar el pedido cuando esté listo o enviado.', 422);
        }
        if (Review::where('order_id', $order->id)->exists()) {
            return $this->error('Ya calificaste este pedido.', 422);
        }

        $photo = null;
        if ($request->hasFile('photo')) {
            $photo = $uploader->upload($request->file('photo'), 'n3xt_review_' . $order->id . '_' . time());
        }

        $productIds = collect($order->models ?? [])->pluck('product_id')->filter()->map(fn ($p) => (string) $p)->values()->all();

        $review = Review::create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'product_ids' => $productIds ?: null,
            'author_name' => $customer->name,
            'rating' => $data['rating'],
            'comment' => trim($data['comment']),
            'photo_url' => $photo,
            'status' => 'pending',
        ]);

        return $this->success(['id' => $review->id, 'status' => $review->status], '¡Gracias! Publicaremos tu reseña después de revisarla.', 201);
    }

    // ─── Admin ───

    public function adminIndex()
    {
        return $this->success(Review::with('order:id,job_name')->latest()->get()->map(fn ($r) => array_merge($r->toArray(), [
            'job_name' => $r->order?->job_name,
        ])));
    }

    public function moderate(Request $request, int $id)
    {
        $data = $request->validate(['status' => 'required|in:pending,approved,rejected']);
        $review = Review::findOrFail($id);
        $review->update(['status' => $data['status']]);

        return $this->success($review);
    }

    public function destroy(int $id)
    {
        Review::findOrFail($id)->delete();

        return $this->success(null, 'Reseña eliminada.');
    }
}
