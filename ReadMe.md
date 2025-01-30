# Redprint - Laravel CRUD Generator with Vue.js

A Laravel package for generating CRUD operations with Vue.js frontend integration. Supports Element Plus, Tailwind CSS, and customizable layouts.

## Installation

```bash
composer require shah-newaz/redprint-ng
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=redprint-config
```

## Requirements

- PHP 8.2+
- Laravel 11+
- Vue.js 3
- The following npm packages must be installed in your project:
  - tailwindcss
  - element-plus
  - axios
  - vue
  - vue-router
  - vue-i18n

## Configuration

The package will look for your Vue router configuration file at the specified location relative to the resources directory. Make sure this path is correctly set before running the CRUD generator command.

### config/redprint.php
```php
return [
    /*
    |--------------------------------------------------------------------------
    | Axios Instance
    |--------------------------------------------------------------------------
    |
    | Specify the axios instance to be used in Vue components.
    | Example: 'this.$api' will generate this.$api.get() instead of axios.get()
    | Set to null to use a local axios instance with proper baseURL.
    |
    */
    'axios_instance' => null,
    
    /*
    |--------------------------------------------------------------------------
    | Vue Router Location
    |--------------------------------------------------------------------------
    |
    | Specify the location of your Vue router configuration file.
    | This should be relative to the resources directory.
    | Example: 'js/router/routes.ts' or 'js/router.js'
    |
    */
    'vue_router_location' => 'js/router/routes.ts',
];
```

## Commands

### Generate CRUD
```bash
php artisan redprint:crud [options]
```

#### Options:
- `--model=` : The name of the model (Required)
- `--namespace=` : The namespace for the controller (Optional)
- `--route-prefix=` : The route prefix (Optional)
- `--soft-deletes=` : Whether to include soft deletes (Optional, default: false)
- `--layout=` : The layout component to wrap the page with (Optional)

#### Example:
```bash
php artisan redprint:crud --model=Product --namespace=API --route-prefix=v1 --soft-deletes=true --layout=DefaultLayout
```

This will generate:
- Model (`app/Models/Product.php`)
- Controller (`app/Http/Controllers/API/ProductController.php`)
- Resource (`app/Http/Resources/ProductResource.php`)
- Migration (`database/migrations/xxxx_xx_xx_create_products_table.php`)
- Vue Components:
  - `resources/js/pages/Product.vue`
  - `resources/js/components/Product/Index.vue`
  - `resources/js/components/Product/Form.vue`
- API Routes in `routes/api.php`
- Common Components (if not exist):
  - `resources/js/components/Common/Empty.vue`
  - `resources/js/components/Common/FormError.vue`
  - `resources/js/components/Common/InputGroup.vue`

### Generate Vue Component
```bash
php artisan redprint:vue [component] [options]
```

#### Options:
- `component` : The component path using dot notation (Required)
- `--layout=` : The layout component to wrap with (Optional)
- `--page` : Whether to generate a page component with router-view (Optional)

#### Examples:
```bash
# Basic component
php artisan redprint:vue resources.js.components.MyComponent

# Component with layout
php artisan redprint:vue resources.js.components.MyComponent --layout DefaultLayout

# Page component
php artisan redprint:vue resources.js.pages.MyPage --page

# Page component with layout
php artisan redprint:vue resources.js.pages.MyPage --page --layout DefaultLayout
```

## Generated API Routes

The following API routes will be generated for each CRUD:

```php
Route::prefix('v1')->middleware(['auth:api'])->group(function () {
    Route::get('products', [ProductController::class, 'getIndex']);
    Route::get('products/{id}', [ProductController::class, 'show']);
    Route::post('products/save', [ProductController::class, 'save']);
    Route::delete('products/{id}', [ProductController::class, 'delete']);
    // If soft deletes enabled:
    Route::delete('products/{id}/force', [ProductController::class, 'deleteFromTrash']);
});
```

## Vue Router Integration

The generated components will automatically integrate with Vue Router, providing the following routes:

```typescript
{
    path: '/products',
    name: 'pages.products',
    component: ProductPage,
    children: [
        {
            path: '',
            name: 'pages.products.index',
            component: ProductIndex,
            meta: {title: 'routes.titles.products', description: 'routes.descriptions.products', requiresAuth: true},
        },
        {
            path: 'edit',
            name: 'pages.products.edit',
            component: ProductForm,
            meta: {title: 'routes.titles.edit_product', description: 'routes.descriptions.edit_product', requiresAuth: true},
        },
        {
            path: 'new',
            name: 'pages.products.new',
            component: ProductForm,
            meta: {title: 'routes.titles.new_product', description: 'routes.descriptions.new_product', requiresAuth: true},
        },
    ],
}
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.



