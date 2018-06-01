# How to: Google API Setup

These instructions apply to the following commands:

- [gsheet-to-xml](https://github.com/forikal-uk/gsheet-to-xml/blob/master/DOCUMENTATION.md) 

In order for our commands to connect to Google's API, we have to do the following steps:

- Create project on https://console.developers.google.com/apis/dashboard.
- Click Enable APIs and enable the Google Sheets API and the Google Drive API
- Go to Credentials, then click Create credentials, and select Service account key
- Choose New service account in the drop down. Give the account a name, anything is fine
- For Role I selected Project -> Editor
- For Key type, choose JSON (the default) and download the file. 
This file contains a private key so be very careful with it, it is your credentials after all. 
Content of the file should be similar to `client_secret.json.dist` 
- Finally, edit the sharing permissions for the spreadsheet you want to access and share either View 
(if you only want to read the file) or Edit (if you need read/write) access to the client_email address you can 
find in the JSON file.


Dev Notes: I followed the instructions above, wrote notes on how to improve the wording and grabbed screen grabs as I did it. So, I (@forikal-uk) shall migrate them over to here at some point. See my notes at: https://github.com/forikal-uk/gsheet-to-xml/issues/6#issuecomment-393713500 
