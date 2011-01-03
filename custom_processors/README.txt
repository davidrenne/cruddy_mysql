One file per cruddy mysql table configuration is written here.

The custom_processors folder creates a series of stub functions meant to allow you to customize each configuration from the code level because as we all know, generic CRUD sometimes is not enough.

You might want to redirect the user somewhere after inserting a new row.

You also might want to update other dependent tables when something changes.

Each function has return true built into it, but you can easily add your own validations and return false on a pre processor to STOP the user from being able to insert.

Functions:

pre_process_load_xxxxxx()

	This function passes the $pointer variable to you.  $pointer is $this->currentAdminDB[CRUD_FIELD_CONFIG] which you can manipulate ANY of your unserialized object before the records are shown.  You might use this to do user specific overrides to the base object such as user preferences and much more.

new_pre_process_xxxxxx() and new_post_process_xxxxxx()

	Pre and Post functions for when the user attempts to insert

update_pre_process_xxxxxx() and update_post_process_xxxxxx()

	Pre and Post functions for when the user attempts to insert

delete_pre_process_xxxxxx() and delete_post_process_xxxxxx()

	Pre and Post functions for when the user attempts to insert