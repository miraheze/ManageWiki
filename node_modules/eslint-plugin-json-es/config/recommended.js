module.exports = {
    rules: {
        // Rules for valid JSON without any style opinion
        'comma-dangle': ['error'],
        'no-dupe-keys': ['error'],
        'no-extra-parens': ['error'],
        'no-loss-of-precision': ['error'],
        'no-undefined': ['error'],
        'no-irregular-whitespace': ['error'],
        'quotes': ['error', 'double'],
        'quote-props': ['error', 'always'],

        // Disable ESLint rules that throw an error for a valid JSON file.
        'accessor-pairs': 'off'
    }
};
