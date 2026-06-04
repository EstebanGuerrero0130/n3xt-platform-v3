<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecurrentCustomer;
use App\Models\RecurrentSupplier;
use App\Traits\ApiResponse;
use App\Http\Requests\StoreContactRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    use ApiResponse;

    // Customers
    public function getCustomers()
    {
        try {
            return $this->success(RecurrentCustomer::orderBy('name')->get());
        } catch (\Exception $e) {
            return $this->error('Error al listar clientes', 500);
        }
    }

    public function storeCustomer(StoreContactRequest $request)
    {
        try {
            $customer = RecurrentCustomer::create($request->validated());
            return $this->success($customer, 'Cliente creado con éxito.', 201);
        } catch (\Exception $e) {
            return $this->error('Error al crear cliente: ' . $e->getMessage(), 422);
        }
    }

    public function updateCustomer(StoreContactRequest $request, $id)
    {
        try {
            $customer = RecurrentCustomer::findOrFail($id);
            $customer->update($request->validated());
            return $this->success($customer, 'Cliente actualizado.');
        } catch (\Exception $e) {
            return $this->error('Error al actualizar cliente', 422);
        }
    }

    public function deleteCustomer($id)
    {
        try {
            RecurrentCustomer::destroy($id);
            return $this->success(null, 'Cliente eliminado');
        } catch (\Exception $e) {
            return $this->error('Error al eliminar cliente', 500);
        }
    }

    // Suppliers
    public function getSuppliers()
    {
        try {
            return $this->success(RecurrentSupplier::orderBy('name')->get());
        } catch (\Exception $e) {
            return $this->error('Error al listar proveedores', 500);
        }
    }

    public function storeSupplier(StoreContactRequest $request)
    {
        try {
            $supplier = RecurrentSupplier::create($request->validated());
            return $this->success($supplier, 'Proveedor creado con éxito.', 201);
        } catch (\Exception $e) {
            return $this->error('Error al crear proveedor: ' . $e->getMessage(), 422);
        }
    }

    public function updateSupplier(StoreContactRequest $request, $id)
    {
        try {
            $supplier = RecurrentSupplier::findOrFail($id);
            $supplier->update($request->validated());
            return $this->success($supplier, 'Proveedor actualizado.');
        } catch (\Exception $e) {
            return $this->error('Error al actualizar proveedor', 422);
        }
    }

    public function deleteSupplier($id)
    {
        try {
            RecurrentSupplier::destroy($id);
            return $this->success(null, 'Proveedor eliminado');
        } catch (\Exception $e) {
            return $this->error('Error al eliminar proveedor', 500);
        }
    }
}
