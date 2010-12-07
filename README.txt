CRUDDY_MYSQL
============
Written By: David Renne
Home Page : http://github.com/davidrenne/cruddy_mysql/tree

FUNCTIONALITIES
============
*Create mySQL Administration Console - w/o people needing to know SQL
	-Also create CRUD recordsets to instantiate and include on other pages outside of this Administration Console

*Configure each field as a unique input type when Creating or Updating records

*Validate the data input using canned validations for common things, or using your own custom regEx

*Create user accounts to log into the console you configure

*Grant permission to view table groups or use it to run websites without writing SQL code

*Custom pre and post processors for each table update/insert allow you to program whatever is needed with your particular needs

*Foreign key lookups on your primary keys allow for business users to recognize the referenced values instead of numbers

*Connect to multiple mySQL servers and abstract a few tables or many tables you wish for people to maintain the data on these records

*Customize which links show based on user roles or just globally shut off based on a table configuration alias

*Stylize some of your tables and data by switching themes

GETTING STARTED
============
If you leave the cruddy_mysql folder with the original name, you dont need to configure anything.

Please ensure that the root directory is writable.  This is where Cruddy Mysql will write three files:

	*crud_{SERVERNAME}.config.php - serialized array storing all your field configs and servers
	*crud_{SERVERNAME}.custom.functions.php - allows you to add custom PHP code when CRUDDY things happen to your database
	*crud_{SERVERNAME}.draw.functions.php - draws all of the tables - normally not edited much
	
Also ensure that cruddy_mysql/cache is writable.

IMPLEMENTATION
============

There are currently two ways to implement cruddy mysql.  One is a full blown administration console for all your tables.  Or secondly, the system will create separate pages and files of which you can integrate in existing HTML templates, wordpress etc.

Step 1: Server Connections

	Open up your browser and attempt to make a connection to 1 or multiple mysql servers.

Step 2: Database Selection

	The next page will prompt you for the databases you wish to use cruddy mysql against.

Step 3: Table Selection 

	All tables must have a primary key in order for cruddy_mysql to work.  If they dont they will be ignored on this page.

	Check the checkbox to the far left if you wish to manage this table through cruddy_mysql.

	You can modify these attributes:

		Table Desc.                            - is the text and name of the object shown to users
		Edit Link Text or Image Src            - Instead of calling it Edit, you could call it "Update" or add an <img> tag for an icon to update
		Delete Link Text or Image Src          - Instead of calling it Delete, you could call it "Remove" or add an <img> tag for an icon to delete
		Referential Integrity On Same Fields?  - This probably isnt the best system for referential integrity enforcement, but if you somehow have unique foreign key field names this 	functionality might work
		Default Order {FIELDNAME} DESC/ASC     - Allows you to specify the column and the ordering when the record viewing is instantiated initially
		WHERE Clause Filter On Read            - Allows you to only show a certain resultset ALWAYS or possibly conditionally when you set cookies and use the cookies in the value of this field
		Description of Filter                  - Allows you to explain the data and what it is.  An example might be "published" records and "unpublished" records where you would create two aliases, one with published=1 and the other with published=0 and the user can differentiate the table configurations and record statuses.
		Hide "XXXX" Link                       - Globally turn on or off any links for a particular table
		Show Paging                            - Turn off the ability to page through records.  May be useful if you just want to show a list of TOP 10 records
		# of Rows Per Page                     - Set your limits on pages
		Number of Pages Linked Ahead	       - Number of page links ahead to jump to
		Required Post Text                     - Indicator of required fields.  (could be an image)

	At the time of posting this page.  1 file will be created in the root directory for each table you configure.  These files are ways in which you could include the system on any php page easily.

Step 4: Table Groups

	The interface for this page could use some more work, but basically I found that when configuring hundreds of tables and showing them all on one page 10 records a piece can make it load slow and make it cluttered.  So I came up with a grouping concept.

	If you have more than 30 tables, it will automatically put them in groups, but will allow you to click OFF and then all the tables will go back to the main bucket.

	Essentially you double click any table and it will go to the main bucket and then you click on the double arrow when selecting from that group into your new group.

Step 5: Setup Roles 

	I allow you to allow functionalities for different roles and also allow you to select the groups those roles can see in the interface.
	
Step 6: Setup Users 

	Add at least one user who can edit all of these pages again as well as any additional people who might use the system
	
Final Step 7: Themes

	Style your tables and data
	
Optional Step, Fields

One important step is to Click on the "Fields" link for each table you wish to configure 

-validation
-input types such as WYSIWYG/select box/file upload
-javascript events for each column
-relationships for lookup foreign keys

	Default Value       - Will allow you to customize values when blank or a new row is being added
	Field Caption       - The name of the column to an end user
	Show Column On Read - Will show the column in the main recordset (be picky about what columns make sense in the record view)
	Read Only           - Upon insert/update, users will see the data but not be able to edit it
	Hide On Insert      - Useful in scenarios like primary keys or other values you dont want the user to see on insert/update
	Required Field      - Enforce a value to not be blank or null
	Lookup Table        - When the key integer value is displayed it will always show the lookup display name instead of the number
	Post Text           - Describe more information when updating/inserting
	Pre-Text (On Read)  - You could do something like <a href="xxx"> on the listing page to start a link or put something else in the record
	Post-Text (On Read) - </a> or maybe an image or something?
	Sortable            - Show the sort link (On by default)
	

Upon configuring everything the system will create a file called crud_{SERVERNAME}.custom.functions.php	

This file is supposed to be used to give you control over when the user updates, deletes, adds etc a record so you can add custom things like email events and other redirection methods to create your administration console for business users who are not savvy enough to navigate something like phpMyAdmin.

Good luck, let me know of your thoughts on this....

OPEN SOURCE PACKAGES USED
============
*FCK Editor  - www.fckeditor.net
*Forms.class - http://www.phpclasses.org/browse/package/1.html
*Crud.class  - http://www.phpclasses.org/browse/package/4273.html

QUESTIONS/COMMENTS
============
david_renne -e-t- yahoo -d-t .com
