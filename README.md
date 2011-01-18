Schema Migration
================

Schema Migration is an extension that tracks actions performed on sections and pages in a convenient way.

- Version: 0.1
- Date: 16th January 2011
- Requirements: Symphony 2.2 or above
- Author: [Rainer Borene](http://rainerborene.com)
- GitHub Repository: <http://github.com/rainerborene/section_migration>

Synopsis
--------

This is the extension that you've always dreamed for Symphony 2. It allows developers to work with other developers without further problems, and more, you can keep content type changes synchronized with the production environment easily. You don't have to touch the database directly any more.

Save your pages and sections as you normally do, and instantly some files will appear in the workspace directory.

Everything happens through simple XML documents. Just send them to the server (or other developers) and boom! Go to System > Migrations and click on the Migrate button.

### Schemas ###

Section schemas are stored in a new folder called `sections` located in your workspace.

Page schemas are stored in a single file called `_pages.xml` inside the pages directory.

**Note:** It's highly recommended to not change schemas directly from a text editor to avoid problems. This extension does this job for you!

Instalattion & Updating
-----------------------

Information about [installing and updating extensions](http://symphony-cms.com/learn/tasks/view/install-an-extension/) can be found in the Symphony documentation at <http://symphony-cms.com/learn/>.

Change Log
----------

**Version 0.1**

- Initial release