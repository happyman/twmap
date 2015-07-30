If you use ajaxCRUD to upload files, this folder should have its permissions (chmod) set to 777 
so it is writable. Otherwise you will get errors like this:

Warning: move_uploaded_file(uploads/9825_04-600x480.jpg) [function.move-uploaded-file]: failed to open stream: No such file or directory in /home/ajaxcrud/public_html/latest_release/ajaxCRUD.class.php on line 1142

Warning: move_uploaded_file() [function.move-uploaded-file]: Unable to move '/tmp/phpqTBRRB' to 'uploads/9825_04-600x480.jpg' in /home/ajaxcrud/public_html/latest_release/ajaxCRUD.class.php on line 1142