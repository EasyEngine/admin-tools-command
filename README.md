easyengine/admin-tools-command
==============================

Commanad to enable disable admin tools for php based sites.



Quick links: [Using](#using) | [Contributing](#contributing) | [Support](#support)

## Using

This package implements the following commands:

### ee admin-tools

Enables/Disables admin-tools on a site.

~~~
ee admin-tools
~~~

**EXAMPLES**

    # Enable admin tools on site
    $ ee admin-tools up example.com



### ee admin-tools install

Installs admin-tools for EasyEngine.

~~~
ee admin-tools install 
~~~





### ee admin-tools up

Enables admin-tools on given site.

~~~
ee admin-tools up [<site-name>] [--force]
~~~

**OPTIONS**

	[<site-name>]
		Name of website to enable admin-tools on.

	[--force]
		Force enabling of admin-tools for a site.



### ee admin-tools down

Disables admin-tools on given site.

~~~
ee admin-tools down [<site-name>] [--force]
~~~

**OPTIONS**

	[<site-name>]
		Name of website to disable admin-tools on.

	[--force]
		Force disabling of admin-tools for a site.

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.


### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/easyengine/admin-tools-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/easyengine/admin-tools-command/issues/new). Include as much detail as you can, and clear steps to reproduce if possible.

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/easyengine/admin-tools-command/issues/new) to discuss whether the feature is a good fit for the project.

## Support

Github issues aren't for general support questions, but there are other venues you can try: https://easyengine.io/support/


*This README.md is generated dynamically from the project's codebase using `ee scaffold package-readme` ([doc](https://github.com/EasyEngine/scaffold-command)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
