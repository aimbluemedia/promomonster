# Running the tests

Each file is a plain PHP script. Run one:

    php tests/plans-test.php

Run all of them:

    for t in tests/*-test.php; do php "$t" | tail -1; done

## The four that need a database

`sending`, `templates`, `reset` and `hosted-reviews` need MySQL or MariaDB,
and parts of `billing` and `click` do too. **Without one they print `SKIP` and
exit 0** — which looks exactly like passing in a loop that only reads the last
line. Two assertions in `sending-test.php` sat red for weeks behind that SKIP,
still expecting the allowances we had before they changed.

So: if a run reports a total, check nothing skipped to get there.

    sudo apt-get install -y mariadb-server
    sudo mysqld_safe &

    mariadb -u root -e "CREATE DATABASE promomonster_test;
      CREATE USER 'pm'@'%' IDENTIFIED BY 'pmtest';
      GRANT ALL ON promomonster_test.* TO 'pm'@'%';"

    for f in database/migrations/*.sql; do
      mariadb -u pm -ppmtest promomonster_test < "$f" || echo "failed: $f"
    done

    export PM_TEST_DB_HOST=127.0.0.1 PM_TEST_DB_PORT=3306 \
           PM_TEST_DB_NAME=promomonster_test \
           PM_TEST_DB_USER=pm PM_TEST_DB_PASS=pmtest

The migration loop is also the only check that the migrations apply cleanly to
an empty schema, in order, with nothing to undo first. `database/full-schema.sql`
is stale — it stops at 012 — so the files themselves are the schema.

The test database is dropped and reseeded by the suites that use it. Point
these variables at a throwaway database, never at anything real.

## Nothing here talks to the internet

No suite sends mail, calls Stripe, or reaches Toutify. The ones that look like
they do are checking what we would have sent.
