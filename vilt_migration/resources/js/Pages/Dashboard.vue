<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    message: String,
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="min-h-screen bg-gray-100 flex flex-col items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Exam Scheduler Dashboard</h1>
            <p class="text-gray-600 mb-6">{{ message }}</p>
            
            <div class="space-y-4">
                <div class="p-4 bg-blue-50 border-l-4 border-blue-500 rounded">
                    <p class="text-sm text-blue-700 font-medium">VILT Stack Migration Status:</p>
                    <p class="text-xs text-blue-600 mt-1">Backend: Laravel 11 (Active)</p>
                    <p class="text-xs text-blue-600">Frontend: Vue 3 + Inertia (Active)</p>
                    <p class="text-xs text-blue-600">Styling: Tailwind CSS (Active)</p>
                </div>
            </div>
        </div>
    </div>
</template>
