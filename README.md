# vsl-audit-canvas-pageviews

Returns all page_view data for a list of Canvas User IDs into individual CSV files. Useful for auditing purposes.

Requirements

* Add Canvas API token to getData.php
* Add Canvas user ids to userids.csv (haven't included it here, as it contains confidential information)

To run:

Run from CLI:
```
php getData.php
```

Will then export CSV files, e.g.

* export\s181234.csv
* export\s201111.csv
* ...etc
