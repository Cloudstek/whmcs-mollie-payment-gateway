# Contributing

Thanks for taking the time to contribute! :+1:

In this document you'll find everything you need to know to start contributing. Your ideas, bug reports and code are all welcome! By following these guidelines you make it a lot easier for maintainers and the community to help you with your contribution.

## Before we get started

### Code of conduct

By participating, you are expected to have read, understand and respect the [code of conduct](CODE_OF_CONDUCT.md). Please report unacceptable behavior to [info@cloudstek.nl](info@cloudstek.nl).

### Sensitive information

When submitting bugs, make sure to leave out any sensitive information (API keys, transaction IDs, customer details...). 

## Contributing

### Reporting bugs

This section helps you to submit a bug report. Following these guidelines helps maintainers and the community to understand your report and reproduce the behavior. Bugs are tracked as [GitHub issues](https://guides.github.com/features/issues/). After you've checked for [existing issues](https://github.com/Cloudstek/whmcs-mollie-payment-gateway/issues), create an issue and provide the following information:

#### Before submitting a bug report

* **Search existing issues.** There might already be issues related to your problem. In case of closed issues that match your problem, reply to them and explain what issues you are still having to re-open the issue.
* **Check if the problem occurs with the default theme**. A theme might include custom code that interferes with payment gateways.
* **Check for conflicting payment gateways**. If you have (previously) enabled any other Mollie payment gateway, it might cause conflicts. Please disable the conflicting payment gateway(s) and re-save the Mollie payment gateway settings.

#### Submitting a bug report

* **Use a clear and descriptive title** for the issue to identify the problem.
* **Describe the steps to reproduce the problem** with as many details as possible. This helps maintainers and the community to reproduce your problem.
* **Include relevant output from the gateway log.** This log contains useful information about transactions and possible exceptions. Please make sure you only include relevant output and output from this gateway only!

Provide more context by answering these questions:

* **Did the problem start happening recently** (e.g. after an update or switching themes) or was this always a problem?
* **Can you reliably reproduce the issue?** If not, tell us how often the problem occurs and under which conditions it usually happens.
* **Can you reproduce the issue with an older version?** If so, what is the most recent version in which the problem doesn't occur?

Include details about your environment:

* **WHMCS version.** This is important to know so we can find out if the problem applies to a specific version of WHMCS or applies to all versions.
* **PHP version.** Important to find out if a problem is related to a specific PHP version.
* **Mollie payment gateway version.** You can find the version in `modules/gateways/mollie/composer.json`.
* **List your enabled addons, theme and other payment gateways with their versions.**

#### Template for submitting bug reports

Also see: [ISSUE_TEMPLATE.md](ISSUE_TEMPLATE.md).

```markdown
[Short description of your problem]

**Steps to reproduce:**

1. [First step]
2. [Second step]
3. [...]

**WHMCS version:** [Enter your WHMCS version]
**PHP version:** [Enter your PHP version]
**Mollie payment gateway version:** [Enter your Mollie gateway version]
**Theme:** [Enter your theme name and details (author, version)]

**Enabled addons:**

[List of enabled addons]

**Enabled payment gateways:**

[List of enabled payment gateways]

**Relevant gateway log output:**

[List of relevant gateway log output]

**Additional information:**

* Problem can be reliably reproduced, doesn't happen randomly: [Yes/No]
* Problem started happening recently: [Yes/No]
* Problem can be reproduced in an older version: [Yes/No]
```

### Suggesting enhancements

Even if writing code is not your cup of tea, your ideas are very welcome. This section helps you suggest an enhancement. Following these guidelines helps maintainers and the community to understand your enhancement suggestion. Enhancement suggestions are tracked as [GitHub issues](https://guides.github.com/features/issues/) and can be found on the [issues page](https://github.com/Cloudstek/whmcs-mollie-payment-gateway/labels/enhancement).

#### Before submitting enhancement suggestions

* **Check you're using the latest version** before suggesting an enhancement. You can find the installed version in `modules/gateways/mollie/composer.json`. The latest release can be found on the [releases page](https://github.com/Cloudstek/whmcs-mollie-payment-gateway/releases).
* **Search existing issues.** There might already be issues related to your enhancement.


#### Suggesting an enhancement

* **Use a clear and descriptive title** for your suggestion.
* **Provide a clear description** of your enhancement suggestion in as many details as possible.
* **Describe the current behavior** and **explain which behavior you would like to see** and why.
* **Explain why this enhancement would be useful** to most users and isn't something that should be implemented as a separate addon or payment gateway.
* **Specify which version of WHMCS you are using.**
* **Specify which version of Mollie payment gateway you are using.**

#### Template for submitting enhancement suggestions

```markdown
[Short description of your suggestion]

**Current behavior:**

[Describe the current behavior here]

**Suggested behavior:**

[Describe the suggested behavior here]

**Why would the enhancement be useful to most users?**

[Explain why the enhancement would be useful to most users]

**Mockups**

![Screenshots, GIFs and mockups which show the suggested behavior](image url)

**WHMCS version:** [Enter your WHMCS version]
**Mollie payment gateway version:** [Enter your Mollie gateway version]
```
### Contributing code

Want to lend a hand but not sure where to start? Check out the [list of open issues](https://github.com/Cloudstek/whmcs-mollie-payment-gateway/issues) to see if there's an issue you'd like to work on. You can also review [open pull requests](https://github.com/Cloudstek/whmcs-mollie-payment-gateway/pulls) to make sure the contributed code is working and complies to the [styleguides](#styleguides).

#### Forking and pull requests

The best way to start contributing code is to fork the repository, make your changes and issue a pull request. To make sure your pull request can be accepted without any problems, check the list below:

* **Include screenshots** or animated GIFs in your pull request when possible.
* **Don't place any PHP files (except for the mollie.php) in the root directory**. Place any additional classes and files in the mollie directory instead.
* **Document your code with with [DocBlocks](https://phpdoc.org/docs/latest/guides/docblocks.html).**
* **Run [CodeClimate](https://github.com/codeclimate/codeclimate) CLI** or run [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) and [PHP Mess Detector](https://phpmd.org) and **fix any issues** to keep the code clean and readable.
* **Use descriptive names** for classes, methods and variables.
* **Read the [styleguides](#styleguides)**
* **Keep your code compatible.** Make sure your code works with all supported versions of WHMCS (currently v6 and v7) and PHP (5.3+). If there is a valid reason to why your code can't be kept compatible, open an issue explaining why. 
* Add yourself to the list of contributors :+1:

### Styleguides

#### Git commit messages

A comprehensive guide of writing good commit messages can be found on the site of Chris Beams: [How to Write a Git Commit Message](http://chris.beams.io/posts/git-commit/). To sum it up:

* Separate subject from body with a blank line
* Limit the subject line to 50 characters
* Capitalize the subject line
* Do not end the subject line with a period
* Use the imperative mood in the subject line
* Wrap the body at 72 characters
* Use the body to explain what and why vs. how

**Additionally:**

* Reference issues and pull requests

#### PHP Styleguide

All code should adhere to certain standards which is checked by [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) and [PHP Mess Detector](https://phpmd.org), which can be run independently or using the [CodeClimate](https://github.com/codeclimate/codeclimate) CLI. Any modern editor should provide on-the-fly linting of your files when working on them so you can address issues before even committing.

* All PHP code should adhere the [PSR-1](http://www.php-fig.org/psr/psr-1/) and [PSR-2](http://www.php-fig.org/psr/psr-2/) coding standards.
* All database tables related to the payment gateway should be prefixed by `mod_mollie_` to prevent conflicts with other addons.
* Sensitive data should be stored encrypted in the database.