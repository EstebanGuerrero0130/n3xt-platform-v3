<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecurrentCustomer;
use App\Models\RecurrentSupplier;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    // Customers
    public function getCustomers()
    {
        return response()->json(RecurrentCustomer::orderBy('name')->get());
    }

    public function storeCustomer(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'customer_id_document' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255',
            'address_full' => 'nullable|string|max:255',
            'city_dept_country' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'location_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string'
        ]);

        $customer = RecurrentCustomer::create($validated);
        return response()->json($customer, 201);
    }

    public function updateCustomer(Request $request, $id)
    {
        $customer = RecurrentCustomer::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'company' => 'nullable|string|max:255',
            'customer_id_document' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255',
            'address_full' => 'nullable|string|max:255',
            'city_dept_country' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'location_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string'
        ]);

        $customer->update($validated);
        return response()->json($customer);
    }

    public function deleteCustomer($id)
    {
        RecurrentCustomer::destroy($id);
        return response()->json(['message' => 'Cliente eliminado']);
    }

    // Suppliers
    public function getSuppliers()
    {
        return response()->json(RecurrentSupplier::orderBy('name')->get());
    }

    public function storeSupplier(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'specialty' => 'nullable|string|max:255'
        ]);

        $supplier = RecurrentSupplier::create($validated);
        return response()->json($supplier, 201);
    }

    public function updateSupplier(Request $request, $id)
    {
        $supplier = RecurrentSupplier::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'specialty' => 'nullable|string|max:255'
        ]);

        $supplier->update($validated);
        return response()->json($supplier);
    }

    public function deleteSupplier($id)
    {
        RecurrentSupplier::destroy($id);
        return response()->json(['message' => 'Proveedor eliminado']);
    }
}
