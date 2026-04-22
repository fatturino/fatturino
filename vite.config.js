import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");
    const host = env.VITE_HOST || "0.0.0.0";
    const port = Number(env.VITE_PORT || 5173);
    const hmrHost = env.VITE_HMR_HOST || host;

    return {
        plugins: [
            tailwindcss(),
            laravel({
                input: [
                    "resources/css/app.css",
                    "resources/js/app.js"
                ],
                refresh: true,
            }),
        ],
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
        },
    };
});
