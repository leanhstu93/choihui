import { defineConfig } from 'vitest/config';

// Test JavaScript thuần (không qua plugin Laravel): npx vitest run
export default defineConfig({
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.js'],
    },
});
