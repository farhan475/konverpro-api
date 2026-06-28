<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\BaTemplate;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaTemplateController extends Controller
{
    use ApiResponse;

    public function __construct() {}

    public function index(): JsonResponse
    {
        $templates = BaTemplate::orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return $this->successResponse($templates);
    }

    public function show(BaTemplate $baTemplate): JsonResponse
    {
        return $this->successResponse($baTemplate);
    }

    public function update(Request $request, BaTemplate $baTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'content_header' => 'sometimes|nullable|string',
            'content_footer' => 'sometimes|nullable|string',
            'logo_url' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $baTemplate->update($validated);

        return $this->successResponse($baTemplate, 'Template berita acara diperbarui.');
    }

    public function setDefault(BaTemplate $baTemplate): JsonResponse
    {
        BaTemplate::where('id', '!=', $baTemplate->id)->update(['is_default' => false]);
        $baTemplate->update(['is_default' => true, 'is_active' => true]);

        return $this->successResponse($baTemplate, 'Template default berita acara diperbarui.');
    }

    public function preview(Request $request, BaTemplate $baTemplate): JsonResponse
    {
        return $this->successResponse([
            'content_header' => $baTemplate->content_header,
            'content_footer' => $baTemplate->content_footer,
            'logo_url' => $baTemplate->logo_url,
        ]);
    }
}
