import path from 'path';
import { defineConfig } from 'vitest/config';

// Kept separate from vite.config.ts on purpose: the build config externalizes
// React and the WordPress packages and pulls in Tailwind, none of which the
// pure-function unit tests need. This config only wires the `@` alias. We run in
// the node environment (not jsdom) since the one function that touches the DOM,
// getCurrency, only reads window.flexaExtra, which its test stubs directly.
export default defineConfig({
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  test: {
    environment: 'node',
    include: ['src/**/*.test.ts'],
  },
});
