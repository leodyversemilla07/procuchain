<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class PrGeneratorController extends Controller
{
    public function index()
    {
        // return Inertia::render('generate-pr');

        return Inertia::render('generate-pr', [
            'csrf_token' => csrf_token(),
        ]);
    }

    public function store(Request $request)
    {
        try {
            // Log received data for debugging
            Log::info('Starting PDF generation with data:', [
                'requestData' => $request->except(['_token']),
                'itemsCount' => count($request->input('items', [])),
            ]);

            // Validate all required fields
            $validated = $request->validate([
                'lgu' => 'required|string',
                'fund' => 'required|string',
                'department' => 'required|string',
                'pr_no' => 'required|string',
                'pr_date' => 'required|date',
                'project_name' => 'required|string',
                'project_location' => 'required|string',
                'purpose' => 'required|string',
                'requested_by_name' => 'required|string',
                'requested_by_designation' => 'required|string',
                'requested_by_date' => 'nullable|date', // Changed from required to nullable
                'budget_officer_name' => 'required|string',
                'budget_officer_designation' => 'required|string',
                'budget_availability_date' => 'nullable|date', // Changed from required to nullable
                'treasurer_name' => 'required|string',
                'treasurer_designation' => 'required|string',
                'cash_availability_date' => 'nullable|date', // Changed from required to nullable
                'approved_by_name' => 'required|string',
                'approved_by_designation' => 'required|string',
                'approved_by_date' => 'nullable|date', // Changed from required to nullable
                'items' => 'required|array',
                'items.*.unit' => 'required|string',
                'items.*.description' => 'required|string',
                'items.*.quantity' => 'required|numeric|min:1',
                'items.*.unit_cost' => 'required|numeric|min:0',
            ]);

            // Calculate total cost per item and format
            $items = [];
            foreach ($validated['items'] as $item) {
                $item['total_cost'] = $item['quantity'] * $item['unit_cost'];
                $items[] = $item;
            }

            // Calculate grand total
            $grandTotal = 0;
            foreach ($items as $item) {
                $grandTotal += $item['total_cost'];
            }

            // Prepare data for the PDF
            $data = [
                'purchaseRequest' => $validated,
                'items' => $items,
                'grandTotal' => $grandTotal,
                'additional_specs' => [] // Add this to prevent undefined variable errors
            ];

            // Log data being sent to view
            Log::info('Data being sent to PDF view', [
                'itemsCount' => count($items),
                'grandTotal' => $grandTotal
            ]);

            // Generate PDF
            $pdf = PDF::loadView('pdf.purchase_request', $data)
                ->setPaper('a4')
                ->setWarnings(false)
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'defaultFont' => 'sans-serif',
                ]);

            // Create directory with proper error handling
            $tempDir = 'temp_pdfs';
            $storagePath = public_path($tempDir);
            
            if (!file_exists($storagePath)) {
                if (!mkdir($storagePath, 0755, true)) {
                    throw new Exception("Failed to create directory: $storagePath");
                }
            }

            // Check if directory is writable
            if (!is_writable($storagePath)) {
                throw new Exception("Directory is not writable: $storagePath");
            }

            // Save PDF to a temporary file with a unique name
            $fileName = 'PR_' . str_replace(['/', '\\', ' '], '_', $validated['pr_no']) . '_' . time() . '.pdf';
            $path = $storagePath . '/' . $fileName;

            // Save the PDF
            $pdf->save($path);

            // Check if file was successfully created
            if (!file_exists($path)) {
                throw new Exception("Failed to create PDF file at: $path");
            }

            // Return the download URL
            return response()->json([
                'success' => true,
                'message' => 'PDF generated successfully',
                'download_url' => url($tempDir . '/' . $fileName)
            ]);
        } catch (Exception $e) {
            // Log the error with detailed info
            Log::error('PDF Generation Error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            // Return error response with specific error message
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}
