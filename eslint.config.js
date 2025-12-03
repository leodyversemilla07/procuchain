import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import typescript from 'typescript-eslint';

/** @type {import('eslint').Linter.Config[]} */
export default [
    js.configs.recommended,
    ...typescript.configs.recommended,
    {
        ...react.configs.flat.recommended,
        ...react.configs.flat['jsx-runtime'], // Required for React 17+
        languageOptions: {
            globals: {
                ...globals.browser,
            },
        },
        rules: {
            'react/react-in-jsx-scope': 'off',
            'react/prop-types': 'off',
            'react/no-unescaped-entities': 'off',
        },
        settings: {
            react: {
                version: 'detect',
            },
        },
    },
    {
        plugins: {
            'react-hooks': reactHooks,
        },
        rules: {
            'react-hooks/rules-of-hooks': 'error',
            'react-hooks/exhaustive-deps': 'warn',
        },
    },
    {
        ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'tailwind.config.js'],
    },
    // MultiChain Smart Filter files - blockchain runtime provides these globals
    {
        files: ['resources/blockchain/filters/**/*.js'],
        languageOptions: {
            globals: {
                // MultiChain Smart Filter API functions
                filterstreamitem: 'readonly',
                filtertransaction: 'readonly',
                getfilterstreamitem: 'readonly',
                getfiltertransaction: 'readonly',
                getlastblockinfo: 'readonly',
                verifypermission: 'readonly',
                verifymessage: 'readonly',
                getassetinfo: 'readonly',
                getstreaminfo: 'readonly',
            },
        },
        rules: {
            // These functions ARE used by MultiChain runtime
            'no-unused-vars': 'off',
            '@typescript-eslint/no-unused-vars': 'off',
            // Catch variable in try/catch is intentionally unused
            'no-useless-escape': 'off',
        },
    },
    prettier, // Turn off all rules that might conflict with Prettier
];
