<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string $material_id
 * @property float $stock_available
 * @property float $low_stock_threshold
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Material $material
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereLowStockThreshold($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereMaterialId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereStockAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereUpdatedAt($value)
 */
	class Inventory extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string $category
 * @property string $type
 * @property string $unit
 * @property numeric|null $density
 * @property numeric $cost_per_kg
 * @property string|null $color
 * @property string|null $location
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Inventory|null $inventory
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material whereCostPerKg($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material whereDensity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material whereUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Material whereUpdatedAt($value)
 */
	class Material extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $project_id
 * @property string $customer_name
 * @property string|null $customer_company
 * @property string|null $customer_id_document
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property string|null $customer_phone_secondary
 * @property string|null $shipping_address
 * @property string|null $shipping_city
 * @property string|null $shipping_zip
 * @property string|null $shipping_reference
 * @property float $volume_mm3
 * @property string|null $dimensions_mm
 * @property float $scale_factor
 * @property float|null $estimated_weight_g
 * @property int $qty
 * @property string $technology
 * @property string $material_id
 * @property string|null $material_name
 * @property int|null $infill
 * @property float $total_price
 * @property float $extras_cost
 * @property array<array-key, mixed>|null $extra_items
 * @property float $estimated_duration_h
 * @property array<array-key, mixed>|null $cost_snapshot
 * @property string|null $comments
 * @property string|null $file_path
 * @property string|null $original_filename
 * @property string $status
 * @property bool $stock_deducted
 * @property bool $hours_added_to_printer
 * @property bool $is_paid
 * @property string|null $tracking_guide
 * @property string|null $tracking_carrier
 * @property int|null $printer_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Printer|null $printer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereComments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCostSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCustomerCompany($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCustomerEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCustomerIdDocument($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCustomerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCustomerPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCustomerPhoneSecondary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereDimensionsMm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereEstimatedDurationH($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereEstimatedWeightG($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereExtraItems($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereExtrasCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereHoursAddedToPrinter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereInfill($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereIsPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereMaterialId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereMaterialName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOriginalFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePrinterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereScaleFactor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereShippingAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereShippingCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereShippingReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereShippingZip($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStockDeducted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTechnology($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTrackingCarrier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTrackingGuide($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereVolumeMm3($value)
 */
	class Order extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $model
 * @property string $technology
 * @property int $watts
 * @property string $status
 * @property float $total_hours_run
 * @property int $maintenance_interval_h
 * @property string|null $last_maintenance
 * @property string|null $next_maintenance
 * @property string|null $maintenance_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $orders
 * @property-read int|null $orders_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer whereLastMaintenance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer whereMaintenanceIntervalH($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer whereMaintenanceNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer whereNextMaintenance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer whereTechnology($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer whereTotalHoursRun($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer whereWatts($value)
 */
	class Printer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $item_name
 * @property string $category
 * @property string|null $material_id
 * @property numeric $units
 * @property numeric $unit_amount
 * @property numeric $qty
 * @property numeric $total_cost
 * @property string|null $supplier
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $purchase_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Material|null $material
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase whereItemName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase whereMaterialId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase wherePurchaseDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase whereSupplier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase whereTotalCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase whereUnitAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase whereUnits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purchase whereUpdatedAt($value)
 */
	class Purchase extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $company
 * @property string|null $customer_id_document
 * @property string|null $email
 * @property string|null $password
 * @property string|null $remember_token
 * @property string|null $phone
 * @property string|null $phone_secondary
 * @property string|null $location
 * @property string|null $address_full
 * @property string|null $city_dept_country
 * @property string|null $zip_code
 * @property string|null $location_reference
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereAddressFull($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereCityDeptCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereCompany($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereCustomerIdDocument($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereLocationReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer wherePhoneSecondary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentCustomer whereZipCode($value)
 */
	class RecurrentCustomer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $contact_person
 * @property string|null $phone
 * @property string|null $specialty
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentSupplier newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentSupplier newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentSupplier query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentSupplier whereContactPerson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentSupplier whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentSupplier whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentSupplier whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentSupplier wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentSupplier whereSpecialty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrentSupplier whereUpdatedAt($value)
 */
	class RecurrentSupplier extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $key
 * @property array<array-key, mixed> $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValue($value)
 */
	class Setting extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $pin_code
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePinCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

