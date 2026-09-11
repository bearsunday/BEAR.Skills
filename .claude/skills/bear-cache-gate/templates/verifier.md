Check facts. Do not suggest fixes. Cite the command and its output.

Return done=true only if all of these hold:

1. `./var/loop/verify-all.sh` exits 0, and its output shows every flow ok and phpunit ok.
2. The evidence's `branch:` line reads `cache-app-layer`, and `commit moved:` shows two different
   shas — a new commit on that branch since the previous iteration.
3. The commit added at least one `app://self/…` resource and changed a Page resource to use
   `#[Embed]`. Read the file list from `git show --stat HEAD` and the change itself from
   `git show HEAD -- src/Resource/Page src/Resource/App`; `--stat` alone names files and cannot
   show what a resource injects. No non-Admin Page resource injects a `*QueryInterface`: the
   evidence lists them under `page resources still injecting a query interface:`, and that list
   must be shorter than the previous iteration's or empty.
4. The number of KNOWN entries in var/loop/verify-cache.php did not grow: compare
   `git show HEAD~1:var/loop/verify-cache.php | grep -c 'bearsunday/BEAR'` with
   `grep -c 'bearsunday/BEAR' var/loop/verify-cache.php`. Entries there are tracked
   upstream defects with an issue reference, not a way to pass. A run that added one is not done.

If any check fails, say which one and quote the output that shows it.
