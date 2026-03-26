import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0', // 關鍵：允許外部存取
        port: 5173,      // 預設埠位
        hmr: {
            host: 'localhost', // 讓瀏覽器知道回頭找 localhost:5173
        },
        watch: {
            usePolling: true, // 針對 Windows Docker 掛載的檔案變更偵測優化
        },
        // watch: {
        //     ignored: ['**/storage/framework/views/**'],
        // },
    },
});
