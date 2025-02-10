# Redprint - Laravel CRUD Generator with Vue.js

A Laravel package for generating CRUD operations with Vue.js frontend integration.

**NOTE:** Redprint is **extremely** opinionated about how you should structure your Laravel + Vue 3 project. It is designed to be used with Laravel 11+ and Vue 3+ using Element Plus and Tailwind CSS.

This package is still in development and may not be fully stable.

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
  - lodash

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
php artisan redprint:crud
```

It will prompt you for the model name, namespace, route prefix, soft deletes, and layout.

#### Example:
```bash
php artisan redprint:crud```

This will generate:
- Model (`app/Models/Product.php`)
- Controller (`app/Http/Controllers/Api/ProductController.php`)
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
php artisan redprint:vue
```

This command allows you to generate three types of Vue components:

1. **Blank Component**: A basic Vue component with minimal setup
2. **List Component**: A data table component with search, sort, and pagination
3. **Form Component**: A form component with validation and API integration

#### Usage Examples:

**Blank Component:**
```bash
php artisan redprint:vue
# Select 'blank' when prompted
# Enter component path (e.g., @/components/views/MyComponent.vue)
```

**List Component:**
```bash
php artisan redprint:vue
# Select 'list' when prompted
# Enter API endpoint (e.g., api/v1/products)
# Define columns when prompted
# Enter component path (e.g., @/components/views/ProductList.vue)
```

**Form Component:**
```bash
php artisan redprint:vue
# Select 'form' when prompted
# Enter API endpoint (e.g., api/v1/products)
# Define columns and relationships when prompted
# Enter component path (e.g., @/components/views/ProductForm.vue)
```

#### Column Definitions

When creating list or form components, you'll be prompted to define columns. Each column can have:

- **Name**: The field name (e.g., 'title')
- **Type**: Data type (string, text, boolean, integer, etc.)
- **Nullable**: Whether the field is required
- **Relationship Data** (optional): For form components with related models
  ```php
  [
      'endpoint' => 'api/v1/categories/list',
      'labelColumn' => 'name',
      'relatedModelLower' => 'categories'
  ]
  ```

#### Generated Components

**Blank Component:**
- Basic Vue 3 component structure
- Script setup syntax
- TypeScript support

**List Component:**
- Element Plus data table integration
- Search functionality
- Pagination
- Column sorting
- Delete/restore actions (if soft deletes enabled)
- API integration with configured axios instance

**Form Component:**
- Form validation
- Dynamic input types based on column definitions
- Related model select inputs (with API integration)
- Save/update functionality
- Error handling
- Loading states

All components are generated with TypeScript support and follow Vue 3's composition API patterns.

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



