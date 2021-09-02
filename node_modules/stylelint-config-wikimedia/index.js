"use strict";

/* Use JSON-style double quotes */
/* eslint quotes: ["error", "double"] */
/* eslint quote-props: ["error", "always"] */
module.exports = {
	"rules": {
		// Wikimedia Foundation ♡ whitespace in its own special way
		// See also https://www.mediawiki.org/wiki/Manual:Coding_conventions/CSS#Whitespace
		"indentation": "tab",
		"max-empty-lines": 1,
		"no-eol-whitespace": true,
		"no-missing-end-of-source-newline": true,

		// Other rules alphabetically
		"at-rule-empty-line-before": [ "always", {
			"except": [
				"blockless-after-blockless",
				"first-nested"
			],
			"ignore": "after-comment"
		} ],
		"at-rule-name-case": "lower",
		"at-rule-name-space-after": "always-single-line",
		"at-rule-no-unknown": true,
		"at-rule-semicolon-newline-after": "always",

		"block-closing-brace-empty-line-before": "never",
		"block-closing-brace-newline-after": "always",
		"block-closing-brace-newline-before": "always-multi-line",
		"block-closing-brace-space-after": "always-single-line",
		"block-closing-brace-space-before": "always-single-line",

		"block-no-empty": true,

		"block-opening-brace-newline-after": "always",
		"block-opening-brace-newline-before": "always-single-line",
		"block-opening-brace-space-after": "always-single-line",
		"block-opening-brace-space-before": "always",

		"color-hex-case": "lower",
		"color-hex-length": "short",
		"color-named": "never",
		"color-no-invalid-hex": true,

		"comment-no-empty": true,
		"comment-whitespace-inside": "always",

		"declaration-bang-space-after": "never",
		"declaration-bang-space-before": "always",

		"declaration-block-no-duplicate-properties": [ true, {
			"ignore": "consecutive-duplicates"
		} ],
		"declaration-block-no-redundant-longhand-properties": true,
		"declaration-block-no-shorthand-property-overrides": true,

		"declaration-block-semicolon-newline-after": "always",
		"declaration-block-semicolon-newline-before": "never-multi-line",
		"declaration-block-semicolon-space-after": "always-single-line",
		"declaration-block-semicolon-space-before": "never",
		"declaration-block-single-line-max-declarations": 1,
		"declaration-block-trailing-semicolon": "always",

		"declaration-colon-space-after": "always",
		"declaration-colon-space-before": "never",
		"declaration-empty-line-before": [ "never", {
			"ignore": [
				"after-comment",
				"inside-single-line-block"
			]
		} ],
		"declaration-no-important": true,

		// `px` values disable accessibility browser setting of user font overrides and
		// should be set in relative units like `em` or `rem` instead.
		"declaration-property-unit-disallowed-list": {
			"font-size": "px",
			"line-height": "px"
		},
		"declaration-property-value-disallowed-list": {
			"/^border/": "none",
			"/^outline/": "none"
		},

		"font-family-name-quotes": "always-unless-keyword",
		"font-family-no-missing-generic-family-keyword": true,
		"font-weight-notation": "named-where-possible",

		"function-disallowed-list": "rgb",
		"function-calc-no-unspaced-operator": true,
		"function-comma-newline-after": "never-multi-line",
		"function-comma-newline-before": "never-multi-line",
		"function-comma-space-after": "always",
		"function-comma-space-before": "never",
		"function-linear-gradient-no-nonstandard-direction": true,
		"function-max-empty-lines": 0,
		"function-name-case": [ "lower", {
			"ignoreFunctions": "/^DXImageTransform.Microsoft.*$/"
		} ],
		"function-parentheses-newline-inside": "never-multi-line",
		"function-parentheses-space-inside": "always",
		"function-url-no-scheme-relative": true,
		"function-url-quotes": "never",
		"function-whitespace-after": "always",

		"length-zero-no-unit": true,
		"linebreaks": "unix",

		"media-feature-colon-space-after": "always",
		"media-feature-colon-space-before": "never",
		"media-feature-name-case": "lower",
		"media-feature-name-no-unknown": true,
		"media-feature-name-no-vendor-prefix": null,
		"media-feature-parentheses-space-inside": "always",
		"media-feature-range-operator-space-after": "always",
		"media-feature-range-operator-space-before": "always",

		"media-query-list-comma-newline-after": "always-multi-line",
		"media-query-list-comma-newline-before": "never-multi-line",
		"media-query-list-comma-space-after": "always-single-line",
		"media-query-list-comma-space-before": "never",

		"no-descending-specificity": true,
		"no-duplicate-selectors": true,
		"no-invalid-double-slash-comments": true,
		"no-extra-semicolons": true,
		"no-unknown-animations": true,

		"number-leading-zero": "always",
		"number-no-trailing-zeros": true,

		"property-case": "lower",
		"property-no-unknown": true,

		"rule-empty-line-before": [
			"always-multi-line", {
				"except": "first-nested",
				"ignore": "after-comment"
			}
		],

		"selector-attribute-brackets-space-inside": "always",
		"selector-attribute-operator-space-after": "never",
		"selector-attribute-operator-space-before": "never",
		"selector-attribute-quotes": "always",
		"selector-combinator-space-after": "always",
		"selector-combinator-space-before": "always",
		"selector-descendant-combinator-no-non-space": true,

		"selector-list-comma-newline-after": "always",
		"selector-list-comma-newline-before": "never-multi-line",
		"selector-list-comma-space-after": "always-single-line",
		"selector-list-comma-space-before": "never",

		"selector-max-empty-lines": 0,
		"selector-max-id": 0,
		"selector-no-vendor-prefix": true,
		"selector-pseudo-class-case": "lower",
		"selector-pseudo-class-no-unknown": true,
		"selector-pseudo-class-parentheses-space-inside": "always",
		"selector-pseudo-element-case": "lower",
		"selector-pseudo-element-colon-notation": "single",
		"selector-type-case": "lower",
		"selector-type-no-unknown": true,

		"string-no-newline": true,
		"string-quotes": "single",

		"time-min-milliseconds": 100,

		"unit-disallowed-list": "rem",
		"unit-case": "lower",
		"unit-no-unknown": true,

		"value-keyword-case": "lower",
		"value-list-max-empty-lines": 0,

		"value-list-comma-newline-after": "never-multi-line",
		"value-list-comma-newline-before": "never-multi-line",
		"value-list-comma-space-after": "always-single-line",
		"value-list-comma-space-before": "never"
	}
};
