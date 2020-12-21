# PaddlePress Sample Theme
This is an example theme for implementing software licensing for WordPress themes. 
It creates a licensing page to activate/deactivate a license key for the domain and maintains the auto-update functionality.

## Local Development Tips
* Rename namespace for updater class, or change the class name.
* Make sure HTTP requests are not blocked to API
* Allow local development domains (`localhost`, `*.test`, `*.local`) for licensing
* Use correct `download_tag` for proper download. Licensing API checks permission for each download item.
