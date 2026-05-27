import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import { svelte } from '@sveltejs/vite-plugin-svelte'
import inertia from '@inertiajs/vite'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");
    const host = env.VITE_HOST || "0.0.0.0";
    const port = Number(env.VITE_PORT || 5173);
    const hmrHost = env.VITE_HMR_HOST || host;

    return {
        plugins: [
            svelte({
                disableDependencyReinclusion: false,
                onwarn(warning, handler) {
                    // This project intentionally bootstraps local form state from props.
                    // Keep useful compiler warnings visible, but silence this noisy one.
                    if (warning?.code === 'state_referenced_locally') {
                        return;
                    }
                    handler(warning);
                },
            }),
            inertia(),
            tailwindcss(),
            laravel({
                input: [
                    "resources/css/fatturino.css",
                    "resources/js/fatturino.js"
                ],
                refresh: true,
            }),
        ],
        resolve: {
            alias: {
                $lib: path.resolve(__dirname, 'resources/js/lib'),
                $layouts: path.resolve(__dirname, 'resources/js/Layouts')
            },
        },
        optimizeDeps: {
            exclude: ['@inertiajs/svelte', 'phosphor-svelte', 'bits-ui', 'layercake'],
        },
        server: {
            host,
            port,
            cors: true,
            hmr: {
                host: hmrHost,
            },
            watch: {
                ignored: ["**/storage/framework/views/**"],
            },
        }
    };
});
