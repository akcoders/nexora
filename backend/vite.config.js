import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/theme.css', 'resources/js/app.js', 'resources/js/datatables.js', 'resources/js/validation.js', 'resources/js/kanban-board.js', 'resources/js/dashboard.js', 'resources/js/report-builder.js', 'resources/js/complaints.js', 'resources/css/library.css', 'resources/js/library.js', 'resources/js/library-editor.js'],
            refresh: true,
        }),
    ],
});
