<?php

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