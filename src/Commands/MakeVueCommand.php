<?php

namespace Shahnewaz\RedprintNg\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeVueCommand extends Command
{
    protected $signature = 'redprint:vue 
        {component : The component path using dot notation}
        {--layout= : The layout component to wrap with}
        {--page : Whether to generate a page component with router-view}';

    protected $description = 'Create a new Vue component';

    private function validateLayout($layout)
    {
        if (!$layout) {
            return;
        }

        $layoutPath = resource_path("js/layouts/{$layout}.vue");
        if (!file_exists($layoutPath)) {
            throw new \Exception("Layout file not found at: {$layoutPath}");
        }
    }

    public function handle()
    {
        try {
            $component = $this->argument('component');
            $layout = $this->option('layout');
            $isPage = $this->option('page');

            // Validate layout if provided
            if ($layout) {
                $this->validateLayout($layout);
            }

            $parts = explode('.', $component);
            
            // Remove 'resources.js' from the beginning if present
            if ($parts[0] === 'resources' && $parts[1] === 'js') {
                array_shift($parts);
                array_shift($parts);
            }

            // Get the component name (last part)
            $componentName = array_pop($parts);
            
            // Build the path
            $path = resource_path('js/' . implode('/', $parts));
            
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            // Choose the appropriate template based on options
            if ($isPage) {
                if ($layout) {
                    $stub = $this->getPageWithLayoutStub($componentName, $layout);
                } else {
                    $stub = $this->getPageStub($componentName);
                }
            } else {
                if ($layout) {
                    $stub = $this->getComponentWithLayoutStub($componentName, $layout);
                } else {
                    $stub = $this->getComponentStub($componentName);
                }
            }

            file_put_contents("{$path}/{$componentName}.vue", $stub);

            $this->info("Vue component {$componentName} created successfully.");
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }

    private function getComponentStub($componentName)
    {
        return <<<VUE
<template>
    <div>
        <p>{$componentName}</p>
    </div>
</template>

<script>
export default {
    name: `{$componentName}`,
    data() {
        return {}
    },
    computed: {
    },
    methods: {
    },
    mounted() {
    },
    components: {
    }
}
</script>
VUE;
    }

    private function getComponentWithLayoutStub($componentName, $layout)
    {
        return <<<VUE
<template>
    <{$layout}>
        <div class="mx-auto">
            <p>{$componentName}</p>
        </div>
    </{$layout}>
</template>

<script>
import {$layout} from "@/layouts/{$layout}.vue"

export default {
    name: `{$componentName}`,
    components: {
        {$layout}
    },
    data() {
        return {}
    },
    computed: {
    },
    methods: {
    },
    mounted() {
    }
}
</script>
VUE;
    }

    private function getPageStub($componentName)
    {
        return <<<VUE
<template>
    <div class="mx-auto">
        <router-view v-slot="{ Component, route }">
            <component :is="Component" :key="route.path"/>
        </router-view>
    </div>
</template>

<script>
export default {
    name: `{$componentName}`
}
</script>
VUE;
    }

    private function getPageWithLayoutStub($componentName, $layout)
    {
        return <<<VUE
<template>
    <{$layout}>
        <div class="mx-auto">
            <router-view v-slot="{ Component, route }">
                <component :is="Component" :key="route.path"/>
            </router-view>
        </div>
    </{$layout}>
</template>

<script>
import {$layout} from "@/layouts/{$layout}.vue"

export default {
    name: `{$componentName}`,
    components: {
        {$layout}
    }
}
</script>
VUE;
    }
}
