const {parseJson} = require('./lib/parseJson');
const recommendedConfig = require('./config/recommended');
const style = require('./config/style');
const rules = require('./rules/index');

module.exports = {
    parseForESLint: parseJson,
    configs: {
        recommended: recommendedConfig,
        style: style
    },
    rules: rules
};
