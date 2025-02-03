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
    | Example: 'resources/js/router/routes.ts' or 'js/routes.js'
    |
    */
    'vue_router_location' => 'resources/js/router/routes.ts',
]; 