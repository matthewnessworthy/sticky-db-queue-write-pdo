# `queue:work` + `sticky` database issue

Following a write job, subsequent jobs use the write PDO instead of the read PDO.

All examples assume `sticky === true` 

First, my definition of correct behaviour is as follows:
DB read calls following a DB write call use the write PDO within the same queue job. Other jobs should use the read PDO *UNLESS* a write action happens within the *SAME* job. Writes in prior jobs *SHOULD NOT* cause the current job to use the write PDO.  

## Scenarios:

### `queue:listen`

Everything works as expected, DB connection is fresh on each call

### `queue:work`

#### 1 thread
Broken, jobs after a write job use the write PDO

#### 2+ threads ( https://laravel.com/docs/8.x/queues#supervisor-configuration )
Extra broken, read jobs on the same process following a write use the write PDO, read jobs on the other processes use the read PDO.

Job chaining switches and maintains the usage of the write pdo after the first write command since the jobs embedded as a chain of jobs and processed as a pseudo single job (afaik). I would consider this correct.

#### 1 thread, `--max-jobs`
Broken, but less ongoing pain since the DB connection is reset after x jobs

## Working examples

I've setup 2 separate databases in order to check for the existasnce of rows in the write connection since inspecting the PDO directly does not show whether is it on the read or write connection (afaik).

Create your databases:
```bash
mysqladmin -uyourusername -p create read_db
mysqladmin -uyourusername -p create write_db
```

Update your .env
```bash
DB_DATABASE_READ=read_db
DB_DATABASE_WRITE=write_db
QUEUE_CONNECTION=redis
```

Migrate both databases:
```bash
php artisan migrate --database=mysql
php artisan migrate --database=mysql_read
```

Truncate the Databases before running the examples
```bash
php artisan example:truncate-databases
```

Update `laravel-worker.conf` with your path settings

run supervisord
```
supervisord -n -c laravel-worker.conf
```

Run some examples
```bash
php artisan example:read-write-read --count_reads=20 --count_writes=1
```

Example output
```
2021-06-11 13:57:23,101 INFO Increased RLIMIT_NOFILE limit to 1024
2021-06-11 13:57:23,103 INFO supervisord started with pid 76673
2021-06-11 13:57:24,113 INFO spawned: 'laravel-worker_00' with pid 76674
2021-06-11 13:57:24,115 INFO spawned: 'laravel-worker_01' with pid 76675
2021-06-11 13:57:24,118 INFO spawned: 'laravel-worker_02' with pid 76676
2021-06-11 13:57:24,121 INFO spawned: 'laravel-worker_03' with pid 76677
2021-06-11 13:57:25,128 INFO success: laravel-worker_00 entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
2021-06-11 13:57:25,128 INFO success: laravel-worker_01 entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
2021-06-11 13:57:25,128 INFO success: laravel-worker_02 entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
2021-06-11 13:57:25,128 INFO success: laravel-worker_03 entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
[2021-06-11 11:57:33][nHjEGPWw6vP0DOg0d7w2M4hRnOCy8OGC] Processing: App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][I2CRsgooQinJmvAHU6tRSjzmsM4XbWGF] Processing: App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][JTEE6gUWDSXm09AmEjMqEJaTC65MoRR5] Processing: App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][dQtT5QFMswmEBLuTbIO7Odq4V2GgQcWF] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][I2CRsgooQinJmvAHU6tRSjzmsM4XbWGF] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][JTEE6gUWDSXm09AmEjMqEJaTC65MoRR5] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][nHjEGPWw6vP0DOg0d7w2M4hRnOCy8OGC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][dQtT5QFMswmEBLuTbIO7Odq4V2GgQcWF] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][b7heicEpFiHL8wh78C7RdInFAMIbFMKm] Processing: App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][uE536nNi4Dekwp37dA875c5D0ZsES7UF] Processing: App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][8nW8TbzPVP4Yk1UUlBTPL5A1HoQt4umS] Processing: App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][j2rgiXxb2wcFEPDDNxgrr0tmMgGk4IjB] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][uE536nNi4Dekwp37dA875c5D0ZsES7UF] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][b7heicEpFiHL8wh78C7RdInFAMIbFMKm] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][8nW8TbzPVP4Yk1UUlBTPL5A1HoQt4umS] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][j2rgiXxb2wcFEPDDNxgrr0tmMgGk4IjB] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][dKLttetcWuRcSIA9xTEW6b2ngFWkv6XX] Processing: App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][r9V6MKxWafsPCzG3UA5O6DDtzl2QJT6l] Processing: App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][mgYwwOCgYA6dLyHdi7WH3b7iONmHTb1K] Processing: App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][4rMYombxTf1seErNUWKSw5vZB8CZbM0z] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][4rMYombxTf1seErNUWKSw5vZB8CZbM0z] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][lKKiaXxBV5JPVTQcRrsSkxrb4EYAAtH1] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][lKKiaXxBV5JPVTQcRrsSkxrb4EYAAtH1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][SjDUzwiGg0LQTBnAuwqLOZaflqDPUCiu] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][SjDUzwiGg0LQTBnAuwqLOZaflqDPUCiu] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][25mTp0MxlQxYBdJTqFjjdRJMyBsS1Vhk] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][25mTp0MxlQxYBdJTqFjjdRJMyBsS1Vhk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][ww4R1lJwV4dmpUN51jUdlwIGgwNnZSf6] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][ww4R1lJwV4dmpUN51jUdlwIGgwNnZSf6] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][u3Up9cVGbAVDezQbdyBelIFnS5T7YdXf] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][u3Up9cVGbAVDezQbdyBelIFnS5T7YdXf] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][NYdXWcIbIzvgEurXL6qT3kVJHrcLvzYk] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][NYdXWcIbIzvgEurXL6qT3kVJHrcLvzYk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][XQLvPBv0Q9Kni6lnVVN0uX4sROYAw2BW] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][XQLvPBv0Q9Kni6lnVVN0uX4sROYAw2BW] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][dKLttetcWuRcSIA9xTEW6b2ngFWkv6XX] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][M53j2yJc3MJdPQG7vAmO6xiEWUIg7k1W] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][M53j2yJc3MJdPQG7vAmO6xiEWUIg7k1W] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][iBhUuXswQt2E3pdiQNlsXdhOzlCkB0ql] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][iBhUuXswQt2E3pdiQNlsXdhOzlCkB0ql] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][92fGpomVywB3AUCk5Orgp9aiV5MtlWTs] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][92fGpomVywB3AUCk5Orgp9aiV5MtlWTs] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][gITdTajRpqToQDjMM4C6pTk2gVOcZXtW] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][gITdTajRpqToQDjMM4C6pTk2gVOcZXtW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][jDPW9178JBOcJzLGGt7okQWwYTPek6ux] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][jDPW9178JBOcJzLGGt7okQWwYTPek6ux] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][CJGrMbYYOByPKW7tirdPqPc7YlphO1mL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][CJGrMbYYOByPKW7tirdPqPc7YlphO1mL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][xoBhcFAht6oDzIOCjjRX6YkDYWQNemvY] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][xoBhcFAht6oDzIOCjjRX6YkDYWQNemvY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][s8DtjGJATTGYjyVx0aMKICIWFJ4K9NI8] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][s8DtjGJATTGYjyVx0aMKICIWFJ4K9NI8] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][r9V6MKxWafsPCzG3UA5O6DDtzl2QJT6l] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][dP9MitejyTi04bOrNEalW3zvm7q6c45Q] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][dP9MitejyTi04bOrNEalW3zvm7q6c45Q] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][Idj6q8HJGNlerwYw3eGqz1QhbrSGVjnC] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][Idj6q8HJGNlerwYw3eGqz1QhbrSGVjnC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][ge6WmA0U9xXtmh4vIYwLtAHQwYNSuYBV] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 11:57:33][ge6WmA0U9xXtmh4vIYwLtAHQwYNSuYBV] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 11:57:33][FufFrK73Z9D27UEdpKpdSAD9JH42m6q8] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 11:57:33][FufFrK73Z9D27UEdpKpdSAD9JH42m6q8] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][s5BSOA6VUoa8YBjXYXkyyqdEl0VZUxrP] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 11:57:33][s5BSOA6VUoa8YBjXYXkyyqdEl0VZUxrP] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][ZxC3sEKYnpglNcP1oPQslTEMecIEKodC] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 11:57:33][ZxC3sEKYnpglNcP1oPQslTEMecIEKodC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][B9ynzpStjTXoV0GwUa5Qu4mpkHi1KeKj] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 11:57:33][B9ynzpStjTXoV0GwUa5Qu4mpkHi1KeKj] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][mgYwwOCgYA6dLyHdi7WH3b7iONmHTb1K] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][pM0yKkXytR1N0nJtuPQHcHhAmJ5WWDZa] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][pM0yKkXytR1N0nJtuPQHcHhAmJ5WWDZa] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][kdrS2flrLBVCJLYdlJFeCijBzRvavYar] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][kdrS2flrLBVCJLYdlJFeCijBzRvavYar] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][Mr4GYSoyCcmaO5Kig0gjEclg6LkRZ4RI] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][Mr4GYSoyCcmaO5Kig0gjEclg6LkRZ4RI] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][G6T7h1m1vw5LXZSX8twi3mhLViZIzq3m] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][G6T7h1m1vw5LXZSX8twi3mhLViZIzq3m] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][27eaU66S4tRLGqWEduc1XhUfBFQakCsP] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][27eaU66S4tRLGqWEduc1XhUfBFQakCsP] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][aY82D2iiWobEwacReIsmcIoEoSSISI33] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][aY82D2iiWobEwacReIsmcIoEoSSISI33] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 11:57:33][aYjea5kWWp0Y4P5CeXVtBkxUQFHPMNAk] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 11:57:33][aYjea5kWWp0Y4P5CeXVtBkxUQFHPMNAk] Processed:  App\Jobs\ReadExpectRead
```

```bash
php artisan example:read-write-read-chain --count_reads=10 --count_writes=1 --count_repeats=50
```

```
2021-06-11 14:20:27,787 INFO Increased RLIMIT_NOFILE limit to 1024
2021-06-11 14:20:27,790 INFO supervisord started with pid 77741
2021-06-11 14:20:28,796 INFO spawned: 'laravel-worker_00' with pid 77742
2021-06-11 14:20:28,798 INFO spawned: 'laravel-worker_01' with pid 77743
2021-06-11 14:20:28,799 INFO spawned: 'laravel-worker_02' with pid 77744
2021-06-11 14:20:28,801 INFO spawned: 'laravel-worker_03' with pid 77745
2021-06-11 14:20:29,807 INFO success: laravel-worker_00 entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
2021-06-11 14:20:29,808 INFO success: laravel-worker_01 entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
2021-06-11 14:20:29,808 INFO success: laravel-worker_02 entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
2021-06-11 14:20:29,808 INFO success: laravel-worker_03 entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
[2021-06-11 12:20:32][1w4VCC4x6Dn5CIxbxvuM3jssoaW1SGrJ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DbVgA9akT76O7r3wDbgH8j7ywPZ0k5gl] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8wHUfFMyfEb59YCthYHP7sUuY0zUT54A] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LEWon6yjkdixVzZloE1fgzOhgTAZa0bC] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][1w4VCC4x6Dn5CIxbxvuM3jssoaW1SGrJ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DbVgA9akT76O7r3wDbgH8j7ywPZ0k5gl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LEWon6yjkdixVzZloE1fgzOhgTAZa0bC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8wHUfFMyfEb59YCthYHP7sUuY0zUT54A] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wcNQcURYQ8TgpBVhHZCm9bluRN4vZ258] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][NT9kjdTKBHjw5yCBnzIUkEf8YdHbLqpi] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8IBcKwIemXPmrQoAJniWInjDU949DfrL] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][68HFopJc3lKUJ2h0KNFK10sUPE5fLrKm] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][wcNQcURYQ8TgpBVhHZCm9bluRN4vZ258] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][NT9kjdTKBHjw5yCBnzIUkEf8YdHbLqpi] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][68HFopJc3lKUJ2h0KNFK10sUPE5fLrKm] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8IBcKwIemXPmrQoAJniWInjDU949DfrL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cmL06Se4uiHNDuogte0UaLONu8cID8ca] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KTdwQnyqu9pPAvyMvvGumLmA5smRNYfp] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][e9J96caBLZNDwsoXuXIQWatepNTxPrCj] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YQ76Z4GSkluaMJss91dJCO0FR16FPBQ7] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][YQ76Z4GSkluaMJss91dJCO0FR16FPBQ7] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qo12S2hudIKZQMCjXNABGONSbacFoH9T] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][qo12S2hudIKZQMCjXNABGONSbacFoH9T] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eYLXd7kGNYxGcGlxM8qSJqiV84sNSCf8] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][eYLXd7kGNYxGcGlxM8qSJqiV84sNSCf8] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Jvm7Z16A0FR4X2WZX7j6kfKsvVGLyiDZ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Jvm7Z16A0FR4X2WZX7j6kfKsvVGLyiDZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TMsmBOPv7lBOwasIQf1Q5KduqazExKLB] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][TMsmBOPv7lBOwasIQf1Q5KduqazExKLB] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][iQVE6L6MnTPa1UZdAGjOpraE5MIB7mLL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][iQVE6L6MnTPa1UZdAGjOpraE5MIB7mLL] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][cmL06Se4uiHNDuogte0UaLONu8cID8ca] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][nQ34c7cEhhcS6SnD3IVcVhDayVLeEGEX] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][nQ34c7cEhhcS6SnD3IVcVhDayVLeEGEX] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ag7UVxfMe9fyS8pMPb0ma0l3zV7QGbi1] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ag7UVxfMe9fyS8pMPb0ma0l3zV7QGbi1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5TOKwN0eYfSW1djjHXBjmx1hJB0jMyqm] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][5TOKwN0eYfSW1djjHXBjmx1hJB0jMyqm] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][D1KiTGcuJaPvZrWkPlIUXSmUpmxH9zfj] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][D1KiTGcuJaPvZrWkPlIUXSmUpmxH9zfj] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][NCDiQuE8q1RtunqM2BHhpjdTSb2goNK9] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][NCDiQuE8q1RtunqM2BHhpjdTSb2goNK9] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ZgN2gZu1FtC7bdrFDJyUICVCGeGF4WEL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][KTdwQnyqu9pPAvyMvvGumLmA5smRNYfp] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][shuDjHzNXkgAvfQ51GfPQnSWE4FW5enU] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][shuDjHzNXkgAvfQ51GfPQnSWE4FW5enU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][kvSLLJKPZ14kFFoELwVRRZjz9DxCJB37] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][kvSLLJKPZ14kFFoELwVRRZjz9DxCJB37] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yEfOd68jdg0AahhBc0D6tbsfP5Poofbb] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][yEfOd68jdg0AahhBc0D6tbsfP5Poofbb] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qz9HGKj3Exd6M37BPkGDCxrLmP1sX6AM] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][qz9HGKj3Exd6M37BPkGDCxrLmP1sX6AM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][VD7R6sxEibNZtwyeENeH9bcyZrnbXCz7] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][VD7R6sxEibNZtwyeENeH9bcyZrnbXCz7] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][U2eDFJ7l9mSeXUtuY3ZpY1d3xPSA5PuL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][e9J96caBLZNDwsoXuXIQWatepNTxPrCj] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][VF2GKUxFgdg82RvOGkmjkXUjayAUpqfL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][VF2GKUxFgdg82RvOGkmjkXUjayAUpqfL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JZ0ckPFtAW7x5nrKHpboFq5HtTXSLeAo] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][JZ0ckPFtAW7x5nrKHpboFq5HtTXSLeAo] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][S0tqKV0YFzgUtptAFo3RyqoTu3n3VdiB] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][S0tqKV0YFzgUtptAFo3RyqoTu3n3VdiB] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vnMM0GclKWrmXmllJ5e6vqGxyixcmPti] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][vnMM0GclKWrmXmllJ5e6vqGxyixcmPti] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xKjcATqNcCS5LgQx6MFg6IULnq1GN73o] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][xKjcATqNcCS5LgQx6MFg6IULnq1GN73o] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mKRpHjbXSXPjDbmNBbqnz19QDEaHmQ2p] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][u8KyfDbYhNwN5jPZsv1DBe0jng2gobya] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][U2eDFJ7l9mSeXUtuY3ZpY1d3xPSA5PuL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mKRpHjbXSXPjDbmNBbqnz19QDEaHmQ2p] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ZgN2gZu1FtC7bdrFDJyUICVCGeGF4WEL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][d1DwkDjE8a1NdUWhhOUR6VdbWLevdMq2] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][u8KyfDbYhNwN5jPZsv1DBe0jng2gobya] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GK86dLkAOlwrboy6NvKQlHiEBsYD2vBA] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vZzbq4AWI6PigoUUR6bfPZiois5sbkVi] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][0C8x6qsKSBTK4qmpo3CLAwxeYT6df1JQ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][d1DwkDjE8a1NdUWhhOUR6VdbWLevdMq2] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GK86dLkAOlwrboy6NvKQlHiEBsYD2vBA] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vZzbq4AWI6PigoUUR6bfPZiois5sbkVi] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][BkIKYIFGQe93RljbT5qt382BLoJeNebY] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GcyCogeJH0ECaHowoOjfdvTilZzObrTq] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fLTad9A6p76XtqPzy4udcAYCpRFQwx0Z] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0C8x6qsKSBTK4qmpo3CLAwxeYT6df1JQ] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][gvzCQjqMjzOdkzcl4iYEijkL0oQfrtIQ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][BkIKYIFGQe93RljbT5qt382BLoJeNebY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fLTad9A6p76XtqPzy4udcAYCpRFQwx0Z] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GcyCogeJH0ECaHowoOjfdvTilZzObrTq] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][fwI71kkGEhjB26YThKZ4ZhxguL6h7ovK] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JnjSiVAtOfb3Q0I0ofGknpkpU3MaplYT] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][gvzCQjqMjzOdkzcl4iYEijkL0oQfrtIQ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eFVBlmFy5AqrdEYpRH6SsPOANaiPufyA] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][xU4uOUfttRWTblU0p80DgfsixcoAHfKe] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fwI71kkGEhjB26YThKZ4ZhxguL6h7ovK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JnjSiVAtOfb3Q0I0ofGknpkpU3MaplYT] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eFVBlmFy5AqrdEYpRH6SsPOANaiPufyA] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Geh2OZ1PE9lHvG66n1KHrNOPZcdKGQF3] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][iqFDd9TIiI8PGnzBSyi9Ovd6bWIqkmSt] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xU4uOUfttRWTblU0p80DgfsixcoAHfKe] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LrLop4SHmXEZMxGdpPNzBWLrgRy9UMOR] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Geh2OZ1PE9lHvG66n1KHrNOPZcdKGQF3] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hODFaU0O2Vd4mLH6kAhA7XhXdrnVnI4L] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][iqFDd9TIiI8PGnzBSyi9Ovd6bWIqkmSt] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LrLop4SHmXEZMxGdpPNzBWLrgRy9UMOR] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][3l2NYNBhQ9w8NtHZxhI2WmZy4fCsT43G] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tk3hOluZ2kw1ZLLX5Qt20rOyRCk6Zxt4] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hjWpI8si6exEHS6sXjBZKC9VdWQbd4GQ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][hODFaU0O2Vd4mLH6kAhA7XhXdrnVnI4L] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][3l2NYNBhQ9w8NtHZxhI2WmZy4fCsT43G] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JGdeV0SXFtVBZMyDNdh1mRN4AvIVlP8f] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hjWpI8si6exEHS6sXjBZKC9VdWQbd4GQ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tk3hOluZ2kw1ZLLX5Qt20rOyRCk6Zxt4] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][V2Q49QbY0wa05isKaLk2I7GewarvwO5w] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][0aeLVwNrFkatfwYw909hY7umsBEZAW0K] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IsIzk9A6iWbXeVCSYmuNvD1tYODvETTI] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JGdeV0SXFtVBZMyDNdh1mRN4AvIVlP8f] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][V2Q49QbY0wa05isKaLk2I7GewarvwO5w] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][pBAxAhZhaPoobwKvjXNIQeS6FfKZfCvg] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3sbIE7XTJy9GsdYNMUoyWKX4O5xWrgHd] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IsIzk9A6iWbXeVCSYmuNvD1tYODvETTI] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0aeLVwNrFkatfwYw909hY7umsBEZAW0K] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][0GGKZteEijsk9sCSmpv8tMwvxuLvyK3y] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tVweqcnR6t72u8AD6oCKhRwOXHuVIejT] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][pBAxAhZhaPoobwKvjXNIQeS6FfKZfCvg] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3sbIE7XTJy9GsdYNMUoyWKX4O5xWrgHd] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][sjLLlQOIOmJVJVsO6iCRTE4k71H68tYJ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TdZq1jOQdXsq8MCZOUHncqoh8Bvx2ODt] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0GGKZteEijsk9sCSmpv8tMwvxuLvyK3y] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tVweqcnR6t72u8AD6oCKhRwOXHuVIejT] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][wyNFDcknBBpicYYMJEyTWWgVQwm2GtYw] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KXKP0kdHR06vtsgz4oOnv5E0E28wxnnv] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TdZq1jOQdXsq8MCZOUHncqoh8Bvx2ODt] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sjLLlQOIOmJVJVsO6iCRTE4k71H68tYJ] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][FS4xwLHL5PUUDakMnE0vytnSN8Vhndef] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wyNFDcknBBpicYYMJEyTWWgVQwm2GtYw] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KXKP0kdHR06vtsgz4oOnv5E0E28wxnnv] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][QC4rgbkA2KfqtWlZ3naIBcQVcZmOCrKn] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ktLpQ0RXaFd1xAGRf9UsLJOjF0kUXI8t] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][BLKTBMc2t2UJsIpOe1zW8uajRGWLDVRf] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][FS4xwLHL5PUUDakMnE0vytnSN8Vhndef] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][QC4rgbkA2KfqtWlZ3naIBcQVcZmOCrKn] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ktLpQ0RXaFd1xAGRf9UsLJOjF0kUXI8t] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DtN7HKdxI9nORb7T97dSkxhIt0tDVvN7] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][BLKTBMc2t2UJsIpOe1zW8uajRGWLDVRf] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][9Ng9Lit0AM9wqAFO8NIXjoO2tDW7Rxl0] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FXvoAOl2Rckc91Gn6FmRGttvDFYEIbYm] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][CXxTsVz5bhMwFmVBeFRThf8GN5ziJp5d] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DtN7HKdxI9nORb7T97dSkxhIt0tDVvN7] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FXvoAOl2Rckc91Gn6FmRGttvDFYEIbYm] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][9Ng9Lit0AM9wqAFO8NIXjoO2tDW7Rxl0] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CXxTsVz5bhMwFmVBeFRThf8GN5ziJp5d] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1FXl7qgTb4Zp2mF7XuknDsOtxjznYykV] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yBI9tLfbn6NO28y7OQDIQcO2JYKW5haU] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][h8623IH0IuoEzaFuNaQeCczZkThdPEfK] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][NkFeFH0CDTL25QcNawNjIU1nRLA7TVCQ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][1FXl7qgTb4Zp2mF7XuknDsOtxjznYykV] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][yBI9tLfbn6NO28y7OQDIQcO2JYKW5haU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][h8623IH0IuoEzaFuNaQeCczZkThdPEfK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][NkFeFH0CDTL25QcNawNjIU1nRLA7TVCQ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TdYxaAeeb332N2te6wSZUxPwQaoIwdzr] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Iy9UL1H3lls3SRPAwBWTB2N9eWkcmpHO] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Ch2ByacIrqXMYRDwrgWBTLktGzyWh0xU] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Idy5WtJ6gtOvrndAinkh9aFeMaOoE3lo] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TdYxaAeeb332N2te6wSZUxPwQaoIwdzr] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Iy9UL1H3lls3SRPAwBWTB2N9eWkcmpHO] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][9KRm4vHsMf1ytncm6X8jGbY7xbhbG142] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Idy5WtJ6gtOvrndAinkh9aFeMaOoE3lo] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Ch2ByacIrqXMYRDwrgWBTLktGzyWh0xU] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][3ZVvIM8CAszvtA6gV8ZSbFX2y2Km9wUy] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vGurJFYRQ3YtD5rvoQPS3HgrCjbxHEIC] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bKCpQR0crhxaaayjdSJtOi2WVEpl8nG9] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][9KRm4vHsMf1ytncm6X8jGbY7xbhbG142] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][3ZVvIM8CAszvtA6gV8ZSbFX2y2Km9wUy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2yQDe4gav9IspyWdXtTnqXzp3JiNJgDd] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bKCpQR0crhxaaayjdSJtOi2WVEpl8nG9] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vGurJFYRQ3YtD5rvoQPS3HgrCjbxHEIC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3BpMh8aPodUjq9GQoNjLMWl1Zpf6WCkZ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][iS3Bgoz9STD1xOrXQVgejLnBz07GZt5K] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Kk9QkwvAd3wulTH5tPXHt06zNuXeiPvk] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2yQDe4gav9IspyWdXtTnqXzp3JiNJgDd] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][3BpMh8aPodUjq9GQoNjLMWl1Zpf6WCkZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5rnVxEMhZVp28TAGFvKBkEM9mh6XnpDP] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][iS3Bgoz9STD1xOrXQVgejLnBz07GZt5K] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Kk9QkwvAd3wulTH5tPXHt06zNuXeiPvk] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][aqdUAuWqYpJHdhkjtK7zrgXXY0oPQedp] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eo4WxTFQBU6SCpMVRaCxJyZFCo6sc8pc] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5rnVxEMhZVp28TAGFvKBkEM9mh6XnpDP] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][hMiTcgayx3kzG6RqoYWS6zCZiJQTTw6t] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][aqdUAuWqYpJHdhkjtK7zrgXXY0oPQedp] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][EhPfdwCTl2Vf6UCrpfzrmhIJP0vQgk52] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][eo4WxTFQBU6SCpMVRaCxJyZFCo6sc8pc] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][hMiTcgayx3kzG6RqoYWS6zCZiJQTTw6t] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ugMN7HcjbvqgXZJWYkcmMNLX4a8Fe2ir] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cnpQTqhgpTP1jzBlQb7HgAw3Ij326Wkn] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][EhPfdwCTl2Vf6UCrpfzrmhIJP0vQgk52] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][6bB8wCM3xBj3rUiUcNyAf7cOMzu16Cic] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Wfygi2io94F0mugMdkYtLFLUawuS52YV] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ugMN7HcjbvqgXZJWYkcmMNLX4a8Fe2ir] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][cnpQTqhgpTP1jzBlQb7HgAw3Ij326Wkn] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6bB8wCM3xBj3rUiUcNyAf7cOMzu16Cic] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Wfygi2io94F0mugMdkYtLFLUawuS52YV] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][oOikpXnR0UoX7VxLzox1ZBgUyQmesCf7] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DJRK9FfT9qgbMxjQymZQBkuptiJiarMf] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YKHb3UsLcFd3SsLPrgE9LM8kvXvKgbSL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][5HY3QqFLl7k4IKGjgJubaUqQ3Ng2zaOH] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][oOikpXnR0UoX7VxLzox1ZBgUyQmesCf7] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DJRK9FfT9qgbMxjQymZQBkuptiJiarMf] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YKHb3UsLcFd3SsLPrgE9LM8kvXvKgbSL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][k7p9IjaRJMRP6fK7EkndhicknfiB12e5] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5HY3QqFLl7k4IKGjgJubaUqQ3Ng2zaOH] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][D5cLNzOStka3ZtNhHsTTIEI0VkPIHbwf] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][C0hoTCM3sxFDtazditVXuUyFByI657nL] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fJkRMn7tsoFT74QjiupUqCvceN67phrh] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][k7p9IjaRJMRP6fK7EkndhicknfiB12e5] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][D5cLNzOStka3ZtNhHsTTIEI0VkPIHbwf] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][yFbDAcSRllCrW7SzvJhRpSrjHlKvqdXz] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fJkRMn7tsoFT74QjiupUqCvceN67phrh] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][C0hoTCM3sxFDtazditVXuUyFByI657nL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tTQgGsqLhtn4W2bNTKepEA7m1lg9QRdT] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][KFuRMXfMskwi5HsDpvEBHhQQYExiKfT2] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ILWB3my5eATpS04FlybyNVja2d19JZno] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yFbDAcSRllCrW7SzvJhRpSrjHlKvqdXz] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][tTQgGsqLhtn4W2bNTKepEA7m1lg9QRdT] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Ycol9yGaRNSRCmZ0YPCIDncuwmiHebxv] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KFuRMXfMskwi5HsDpvEBHhQQYExiKfT2] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ILWB3my5eATpS04FlybyNVja2d19JZno] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vn0JKY3bgQ5Hbad0FTzOOcSHNN4hxlrJ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][vn0JKY3bgQ5Hbad0FTzOOcSHNN4hxlrJ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][13dQcovOR279pGJztGmk8MMsnA8mMLFp] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][13dQcovOR279pGJztGmk8MMsnA8mMLFp] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][uIz3Pf2c6TLW7AVtwRWNWWLTHmFUoTd1] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][uIz3Pf2c6TLW7AVtwRWNWWLTHmFUoTd1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][jXo5D3A9vq22jaiaUcpvyPl43fkok01D] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][jXo5D3A9vq22jaiaUcpvyPl43fkok01D] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hKAgo58bxhxbNSjNYvGo6P2auLsRmYZ2] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][hKAgo58bxhxbNSjNYvGo6P2auLsRmYZ2] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OOz6RxZv2ZF3RCCaRubzys3EhZfxhfqZ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][psXZg2VvizPmIsDiN2Idi7YumMp2cf2n] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][psXZg2VvizPmIsDiN2Idi7YumMp2cf2n] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zEcFow7o93HekafGYN41WDrOBNjZVpfJ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][zEcFow7o93HekafGYN41WDrOBNjZVpfJ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][pJn9hcNIiqNHkF3KQXbohTHaUxQwZ1Zf] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][pJn9hcNIiqNHkF3KQXbohTHaUxQwZ1Zf] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][D4wBXkH09w8P4hXZOY0s0hHQdAbyNxIX] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][D4wBXkH09w8P4hXZOY0s0hHQdAbyNxIX] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][byv17MiJXNjxc6wJHuFIKo3NEaMz5577] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][byv17MiJXNjxc6wJHuFIKo3NEaMz5577] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][gJW4wvOnXKRcUouD9qPWtkLN1ubWyTC3] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][gJW4wvOnXKRcUouD9qPWtkLN1ubWyTC3] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][m1gfYxk242PKpxXKsY0JiGXjkIcVVodS] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Ycol9yGaRNSRCmZ0YPCIDncuwmiHebxv] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][N31gJln2gJM9QF5Xt2Cr3EkZxDGAKhCJ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][N31gJln2gJM9QF5Xt2Cr3EkZxDGAKhCJ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OfgtlyiTcMhwo7j5GOz6QDIpqvHPpeMA] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][OfgtlyiTcMhwo7j5GOz6QDIpqvHPpeMA] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][n6dnWqjS2BZC1QPUPC01iBybkmpycUYG] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][n6dnWqjS2BZC1QPUPC01iBybkmpycUYG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][kNjuu2bnj6XmSY3rMmIvQC3T0vicFIbg] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][kNjuu2bnj6XmSY3rMmIvQC3T0vicFIbg] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][slsCGcoRZfG5tQhxuaiA6DLy1yHJuBHq] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][slsCGcoRZfG5tQhxuaiA6DLy1yHJuBHq] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4fSar4cKwf5a07EcnJwX9MKOKcOtwlqq] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][B78QCcxl6AKQYBbXyXf60rc149uu0mpP] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][B78QCcxl6AKQYBbXyXf60rc149uu0mpP] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][r8zi5z1CTJdTu9hRQOe3ndgUuXaB2Z2Y] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][r8zi5z1CTJdTu9hRQOe3ndgUuXaB2Z2Y] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0bmTcrZJJGbZxBY9A77V6feeceUgj0vy] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][0bmTcrZJJGbZxBY9A77V6feeceUgj0vy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xxCMPZKq1tp7FqLAZw28OAGvtytQOHMV] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][xxCMPZKq1tp7FqLAZw28OAGvtytQOHMV] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][SsvrfmzZ67iAGrIJRWGreeoPOpMsuFtK] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][SsvrfmzZ67iAGrIJRWGreeoPOpMsuFtK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][nhGtxujaq5SM2BAPxDMCtsOKAQC3hmxO] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][OOz6RxZv2ZF3RCCaRubzys3EhZfxhfqZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][nhGtxujaq5SM2BAPxDMCtsOKAQC3hmxO] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][4fSar4cKwf5a07EcnJwX9MKOKcOtwlqq] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][h2cT2qKjI3xfKDdwL1aK4r0sFzfjcVoG] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][rLp95OgQyv5AbiwoPQtjzzoWy4YgVsDz] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][m1gfYxk242PKpxXKsY0JiGXjkIcVVodS] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xt4hgWkq2jwJ9FJQsteG0GNUruWUfDHS] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][uAWnQ9HOjuZ2btfRM8fM1aWu34QAgqaM] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][rLp95OgQyv5AbiwoPQtjzzoWy4YgVsDz] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][h2cT2qKjI3xfKDdwL1aK4r0sFzfjcVoG] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][xt4hgWkq2jwJ9FJQsteG0GNUruWUfDHS] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][952Se6rAFiY5luRf0VmrmoApTukgWCpg] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4uFtsSATxwroz0GC6Ilv3PNcabxewlAZ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][uAWnQ9HOjuZ2btfRM8fM1aWu34QAgqaM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][7RYpOOB7j2yJQDinZJu9MOjzyOINfwJP] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][8UJyYDYi1Mxwz332XcYArhsDg4zjk7xL] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][952Se6rAFiY5luRf0VmrmoApTukgWCpg] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][4uFtsSATxwroz0GC6Ilv3PNcabxewlAZ] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][zI7plovmlnhPnUSCfM8wahmxDUDGs4Gl] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][7RYpOOB7j2yJQDinZJu9MOjzyOINfwJP] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8UJyYDYi1Mxwz332XcYArhsDg4zjk7xL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KfeHl4xJb3j7pxn4H3cpkhen7MIKvUU6] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Zs1GdA6nLOGUYfZiuumEP4DlVFkgxtkD] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][JCa0UFL9OzSQFnX2MB1HTitPzPkhRmrZ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zI7plovmlnhPnUSCfM8wahmxDUDGs4Gl] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][KfeHl4xJb3j7pxn4H3cpkhen7MIKvUU6] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Zs1GdA6nLOGUYfZiuumEP4DlVFkgxtkD] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0xKFZ1dYsu3hHNWhsF0snSDKDQUDJwXg] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JCa0UFL9OzSQFnX2MB1HTitPzPkhRmrZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lX0FM0qN7plOx8FFYD1b67qn4FKgdcBU] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Nh6yr45B1NIPdVW2Cq2ftkUHhyAekJdn] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Pg7YytXenKKn0vb9lnUI8EeAiWFzfkRt] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][0xKFZ1dYsu3hHNWhsF0snSDKDQUDJwXg] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][lX0FM0qN7plOx8FFYD1b67qn4FKgdcBU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Nh6yr45B1NIPdVW2Cq2ftkUHhyAekJdn] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Pg7YytXenKKn0vb9lnUI8EeAiWFzfkRt] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DkxxvrYDiAJ99E1rj5V1JfCqZ6KtcOMG] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wnBeLXQjmRiPgCykQUVEEvRffPBnfiPZ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mUTBacjZxyL6ehcw28h2Glb5cUcvfnaJ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lXqnHPAc7a9xMTASgNZ11AorC2nenghR] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DkxxvrYDiAJ99E1rj5V1JfCqZ6KtcOMG] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][wnBeLXQjmRiPgCykQUVEEvRffPBnfiPZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mUTBacjZxyL6ehcw28h2Glb5cUcvfnaJ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lXqnHPAc7a9xMTASgNZ11AorC2nenghR] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ECsoShrmIg5GEKnjnuO4fl2WsYSDep1s] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1oja0oNIhnVB2w30VNaMLFJ1HLrRxlCw] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][a6BATNkAkPSCCP0p713esx7r2TY6m4AI] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][fDj4JGDviC0G9IAt01GA9aVzOXZ9cmwD] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ECsoShrmIg5GEKnjnuO4fl2WsYSDep1s] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][a6BATNkAkPSCCP0p713esx7r2TY6m4AI] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][1oja0oNIhnVB2w30VNaMLFJ1HLrRxlCw] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PPDYaxS7zPEruU1JcoxNi2xzwDcMwlR2] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fDj4JGDviC0G9IAt01GA9aVzOXZ9cmwD] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0aqcaYWYHd2prErShxPOnwEiYxbQqBmC] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2ERPH9mqMoHdXvW3prcg0aT7MU95JfDW] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][nE0CJyUlSPcJp3DkKVnMFHH5MJ345lxq] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][PPDYaxS7zPEruU1JcoxNi2xzwDcMwlR2] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][nE0CJyUlSPcJp3DkKVnMFHH5MJ345lxq] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2ERPH9mqMoHdXvW3prcg0aT7MU95JfDW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0aqcaYWYHd2prErShxPOnwEiYxbQqBmC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][jkXLFw1vN5hyvzr4iLMjxNG47l0ytGI1] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0E8tyyaI4NEiTAuc5IB93aTOdAPZMaCt] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WdG404ksJXQEmlLQakElD6Uneoe2n4zf] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3MBWieJCWKxORhSAh41eoZ414arvfRwY] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][jkXLFw1vN5hyvzr4iLMjxNG47l0ytGI1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3MBWieJCWKxORhSAh41eoZ414arvfRwY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0E8tyyaI4NEiTAuc5IB93aTOdAPZMaCt] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WdG404ksJXQEmlLQakElD6Uneoe2n4zf] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tnndCwyZPzSijdbMnF6VDBsT0JDeDljf] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IJVW6nnVTk9wyMNsZv71qCQug1tNAh7g] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6Qx1tUk3iujy5LmehV5VMqVs5aPjYwjW] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][xvPlUJIdypocWNTOb5qVEkJ5eNW7Ti99] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][tnndCwyZPzSijdbMnF6VDBsT0JDeDljf] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][IJVW6nnVTk9wyMNsZv71qCQug1tNAh7g] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6Qx1tUk3iujy5LmehV5VMqVs5aPjYwjW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xvPlUJIdypocWNTOb5qVEkJ5eNW7Ti99] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bK8QjfxS99OcuqPvv4vDG1gvHByWwGdP] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PtygPWiCNryTj4ET1ZErUnXFqisNbWCF] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][uV1uQqRQTmKWC0U9C2Sa980UGXLnHPSI] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Z31gv50qoIemZeY5raoVhw5lr43A96CW] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][bK8QjfxS99OcuqPvv4vDG1gvHByWwGdP] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][PtygPWiCNryTj4ET1ZErUnXFqisNbWCF] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Z31gv50qoIemZeY5raoVhw5lr43A96CW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][uV1uQqRQTmKWC0U9C2Sa980UGXLnHPSI] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PBflAhHTqT0q2ye8iLjJMIHngZL4vHz6] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5KK7K9QZCkIHOuGTuELtcKieJxdbtSlP] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cfW40gjRkQa2yPsLY4BsLOsr0oQctCTt] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][fhbwxaW1l28GKrRB4LoAHiEyrLsXCX2W] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][PBflAhHTqT0q2ye8iLjJMIHngZL4vHz6] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5KK7K9QZCkIHOuGTuELtcKieJxdbtSlP] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][cfW40gjRkQa2yPsLY4BsLOsr0oQctCTt] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GrUYbwyqpXGGiZzq3TIZu2LXcKc4uqWN] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PRGV9vBDcZUNyy0KWlEgPIqP22S24iha] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fhbwxaW1l28GKrRB4LoAHiEyrLsXCX2W] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Vmbvp6RN7HWO0WxyVM4n2JjzI5EBkiHC] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][YLu8mDHjAMzzjp2xapJFd8yU47gkESBZ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GrUYbwyqpXGGiZzq3TIZu2LXcKc4uqWN] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][PRGV9vBDcZUNyy0KWlEgPIqP22S24iha] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][UnnBR9KItwOxtApSTzMsZQRWfr6yqF5L] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Vmbvp6RN7HWO0WxyVM4n2JjzI5EBkiHC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YLu8mDHjAMzzjp2xapJFd8yU47gkESBZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tY1IJ05PltDVNZzz9oAQ4Xhe3D5ZEsBT] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][qXOLmnAQLl0vBN39nMaq6dFUfcTdyuj8] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][UnnBR9KItwOxtApSTzMsZQRWfr6yqF5L] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][poYxeXujn50t7KZvPVYXxhfRdd791Xpu] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][tY1IJ05PltDVNZzz9oAQ4Xhe3D5ZEsBT] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][eBEkleW2oY5ldNYBkOd6pzr0pE95eGiE] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qXOLmnAQLl0vBN39nMaq6dFUfcTdyuj8] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][zx3UbLvgVJhxgKar16NeHgoYtaFU0KPX] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][poYxeXujn50t7KZvPVYXxhfRdd791Xpu] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][9On523AayEBjYzQm05VKOd0N5gvQSInr] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eBEkleW2oY5ldNYBkOd6pzr0pE95eGiE] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cxof8lZcoYcjsN56EvkMGTZoOO51Gmse] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zx3UbLvgVJhxgKar16NeHgoYtaFU0KPX] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][uD2AAv4LF0a0BuRWAoozwpvS3lH3sYIt] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WxI5E5eANcE5M3NCfoLcUpH0fgpx6vhP] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][9On523AayEBjYzQm05VKOd0N5gvQSInr] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cxof8lZcoYcjsN56EvkMGTZoOO51Gmse] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][kt4PE32BlbwNp629kuHsh02x0rhDxLvu] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vzA2HXyDFCWuZme8k9QVS320jicPKFbn] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][uD2AAv4LF0a0BuRWAoozwpvS3lH3sYIt] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WxI5E5eANcE5M3NCfoLcUpH0fgpx6vhP] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][bTBKglbXRSljQbZKWPiLk4e1SGBJQa1T] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][kt4PE32BlbwNp629kuHsh02x0rhDxLvu] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vzA2HXyDFCWuZme8k9QVS320jicPKFbn] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][RuxajWqMnNNeIF8Sqn4CQFdrwKNnqUQ6] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][rALuxrI2S4XMcDz5y5utXlF4KLwvdXqs] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][bTBKglbXRSljQbZKWPiLk4e1SGBJQa1T] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FVBfIKGjRzRsumjU8WhqjwY0UJwq7nag] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][RuxajWqMnNNeIF8Sqn4CQFdrwKNnqUQ6] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Xc2GMyGX5pBpta2xVOB3b91mCBUAkYwG] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][rALuxrI2S4XMcDz5y5utXlF4KLwvdXqs] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sgXAckF3QVe0wUltczTVup7A2haMqGSY] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FVBfIKGjRzRsumjU8WhqjwY0UJwq7nag] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][3vVxnpnJ37TFgYJ0RVtdfC74Df7ynnh5] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sgXAckF3QVe0wUltczTVup7A2haMqGSY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5Gsp9ANj0wcOeWf7Pt3zhgMLlqUe8GD0] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Xc2GMyGX5pBpta2xVOB3b91mCBUAkYwG] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Qmccfy5Sjxok2Vip3hrrjrS1DAELcHn3] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vCO3PNJ7yLyR5ycK3tPwD9LjUjw6eAX4] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3vVxnpnJ37TFgYJ0RVtdfC74Df7ynnh5] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5Gsp9ANj0wcOeWf7Pt3zhgMLlqUe8GD0] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DbGu5zFeHy1du7fTnpsDbKQ76oI2OKPx] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Qmccfy5Sjxok2Vip3hrrjrS1DAELcHn3] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DXsB1U98Ybqf77oLBIoyotbVmvL51uLE] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][vCO3PNJ7yLyR5ycK3tPwD9LjUjw6eAX4] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DbGu5zFeHy1du7fTnpsDbKQ76oI2OKPx] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][WDdc9hKwACy1YXXMGoHLA1tv93KAtP2B] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DxrqjSMOvYs7NbBX7EgwfuGdGPXiFRdG] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][kqAe7jSSCsXYV3vqsx0pK7yF4Cxl2xqM] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][kqAe7jSSCsXYV3vqsx0pK7yF4Cxl2xqM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8vZRUUF4ciDsCeIwhR6mj9k9tioEVbBO] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][8vZRUUF4ciDsCeIwhR6mj9k9tioEVbBO] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Oh9pi7QHqfxurt3khPz41BNt4U7d3FCK] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Oh9pi7QHqfxurt3khPz41BNt4U7d3FCK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OaC42YeJq1P6GdfmWlKmdK2oDmxV7jue] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][OaC42YeJq1P6GdfmWlKmdK2oDmxV7jue] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][w19D8dEmuf97GrgGg8UbnhACaedTUztE] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DXsB1U98Ybqf77oLBIoyotbVmvL51uLE] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Bl0G1HlLsr8tktQ32haDSUTEkVhmIb5r] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Bl0G1HlLsr8tktQ32haDSUTEkVhmIb5r] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WlfGwyZMggUjI6NMavqMLF7AEnFu4vTw] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][WlfGwyZMggUjI6NMavqMLF7AEnFu4vTw] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][l0DwVISRSKAj2UazgFV8HQcU9TKOhjvz] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][l0DwVISRSKAj2UazgFV8HQcU9TKOhjvz] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MrcU07dKSdqRZUAJ085IOBIMUcre8CG5] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][MrcU07dKSdqRZUAJ085IOBIMUcre8CG5] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ilK91XtJ7MLfl9jwKM0ehJHlKHc27pYZ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][WDdc9hKwACy1YXXMGoHLA1tv93KAtP2B] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OdAe7l6ksEyRzww1ijaGAlPwHkXQ7cqV] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][OdAe7l6ksEyRzww1ijaGAlPwHkXQ7cqV] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][HsGDWDsYq6sufGwlboxCml83dk4Gv2hU] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][HsGDWDsYq6sufGwlboxCml83dk4Gv2hU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bFVoiqrV5f4ChOzUVFmgC5u6nV6DTVry] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][bFVoiqrV5f4ChOzUVFmgC5u6nV6DTVry] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WZskx5BCuv50dtGfwoW4pwHNmj492D5u] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DxrqjSMOvYs7NbBX7EgwfuGdGPXiFRdG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][maUqDSInF7LP66MyhQmODRR09kcjHEIX] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][maUqDSInF7LP66MyhQmODRR09kcjHEIX] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DegPFdmeoDlpq51Ir82ehmoW6AG6E7aF] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DegPFdmeoDlpq51Ir82ehmoW6AG6E7aF] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1JutbB7zIyUCOXZn5nCnVUpnhjVAIzIJ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][1JutbB7zIyUCOXZn5nCnVUpnhjVAIzIJ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][rmSF0pJjR9H9qnOxb9zghWAL6zjK1qH1] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][rmSF0pJjR9H9qnOxb9zghWAL6zjK1qH1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hnDaBfPQroixY3DaOrn17N2At1hYNyqA] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][w19D8dEmuf97GrgGg8UbnhACaedTUztE] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WZskx5BCuv50dtGfwoW4pwHNmj492D5u] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ilK91XtJ7MLfl9jwKM0ehJHlKHc27pYZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LLw3N4tY9FbHUzsI3UhGIhgOGFVi6kBx] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hnDaBfPQroixY3DaOrn17N2At1hYNyqA] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ueNDoykoS71746Hz8K5wyjlUkZpjtnOJ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][xFDNduWB0FIMNCKnvmHpjtrri3kC5yZi] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][49LBpGuTDdZx3CFLkfFbSeYMloOWlTQv] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][LLw3N4tY9FbHUzsI3UhGIhgOGFVi6kBx] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ueNDoykoS71746Hz8K5wyjlUkZpjtnOJ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xFDNduWB0FIMNCKnvmHpjtrri3kC5yZi] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Vefe7gINlNSLzyCLUViVwA0o334PGIxO] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][49LBpGuTDdZx3CFLkfFbSeYMloOWlTQv] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][s42KkuuzhF3jIQBA5uRR7d7b4VZHgcyx] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][dj9tKofoLdTFEpR1zMPrktvEKtN9TPui] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8ZUVZSFqmDAEaL8jQgVAbbroVaBQV7hi] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Vefe7gINlNSLzyCLUViVwA0o334PGIxO] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][s42KkuuzhF3jIQBA5uRR7d7b4VZHgcyx] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bVaYuJ1b3dXzRFUI1RzJO9TfO3iC67rc] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][dj9tKofoLdTFEpR1zMPrktvEKtN9TPui] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8ZUVZSFqmDAEaL8jQgVAbbroVaBQV7hi] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][e4LMQElmBoqr72WC984cJsRzQHoTn5HU] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][wZPxIbEF3V8Yqcbs7qMYLbuTWPrgTCOm] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wnbLGhgJYdwAKaaj6oFoGgQoWlbm6omW] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bVaYuJ1b3dXzRFUI1RzJO9TfO3iC67rc] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][1KRZ2bti0kHaAQGM11qGdCOOcCudPbwo] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wZPxIbEF3V8Yqcbs7qMYLbuTWPrgTCOm] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][e4LMQElmBoqr72WC984cJsRzQHoTn5HU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wnbLGhgJYdwAKaaj6oFoGgQoWlbm6omW] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][biRHv9VZwrhKMDnm4Psa7oHbIJvFrACi] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JSM2vL2esbv1tnE7mIEHgZ5K8k2SMGJ6] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1KRZ2bti0kHaAQGM11qGdCOOcCudPbwo] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FTGZZOKN4pimBQc5353DGzbVj1Sbbnde] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][biRHv9VZwrhKMDnm4Psa7oHbIJvFrACi] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][d5OwphiiHyiA3yu6gtugAbsSuYlrCr3Y] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JSM2vL2esbv1tnE7mIEHgZ5K8k2SMGJ6] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FTGZZOKN4pimBQc5353DGzbVj1Sbbnde] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][6sLlHKfo1jUGqUYG65fTmeKkSEdHTpht] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FbKafFTRHdhPApB9qdc7Q9zW72a5tUlF] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][d5OwphiiHyiA3yu6gtugAbsSuYlrCr3Y] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][n4P1WxLF1OUT0dqDur9YImqRPLD7aSGJ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6sLlHKfo1jUGqUYG65fTmeKkSEdHTpht] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FbKafFTRHdhPApB9qdc7Q9zW72a5tUlF] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][pgrbYgySW68J5On4nfCWXPamAZWi9Xfa] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1KGfE4xCz36epTKSPvf5BYCOmxqIVguS] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hCDfrsjwznyuxQHhU6i0Wu6U0efJG52Z] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][n4P1WxLF1OUT0dqDur9YImqRPLD7aSGJ] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][pgrbYgySW68J5On4nfCWXPamAZWi9Xfa] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hCDfrsjwznyuxQHhU6i0Wu6U0efJG52Z] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1KGfE4xCz36epTKSPvf5BYCOmxqIVguS] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][X9PO3AgsiRluvR6onP8QJkwJDphzSPME] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JI7S5jOAXb4hMzJPgP4rUbFlmrqDoBON] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][axNBiXZdEAoKaQJQWlO5uNYoL3XoD16p] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][zTMhZcOnMVdQRI9JlRuGb0C1xzt5TtII] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][JI7S5jOAXb4hMzJPgP4rUbFlmrqDoBON] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][X9PO3AgsiRluvR6onP8QJkwJDphzSPME] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][axNBiXZdEAoKaQJQWlO5uNYoL3XoD16p] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2Qrd21YeY4UzAsJi0i1ptTOHMVbVQgZf] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][XnskpfDqg8iCZOvYu0yIeZXyfHZca5TY] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zTMhZcOnMVdQRI9JlRuGb0C1xzt5TtII] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][9x7hc0GUX6eFXdK0D7HvFbDI1wVALv2u] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DR5tvHcYQj6f3OMlA9FKCN4QwD56sEru] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][XnskpfDqg8iCZOvYu0yIeZXyfHZca5TY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2Qrd21YeY4UzAsJi0i1ptTOHMVbVQgZf] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][9x7hc0GUX6eFXdK0D7HvFbDI1wVALv2u] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ZIuyd6q2Fs4omujdCXegr2evfTQIoYhA] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DR5tvHcYQj6f3OMlA9FKCN4QwD56sEru] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qbfKFEJXQ7vGf0b8FRI2iLQVK5GFHuMD] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ETs3XyX5YLtPivpevdoZ2pevhEONXysM] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][6c7vVd48m6PrTNPI2gAWXiQqDuPSPa9S] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ZIuyd6q2Fs4omujdCXegr2evfTQIoYhA] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ETs3XyX5YLtPivpevdoZ2pevhEONXysM] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][qbfKFEJXQ7vGf0b8FRI2iLQVK5GFHuMD] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5lEh891R2TzVENV3rtVy5CwdSK7QDIAN] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6c7vVd48m6PrTNPI2gAWXiQqDuPSPa9S] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cMftmK1jJgpYFaOXn9VxHP1prPfIL4Ck] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][s7QwFe0Zy5VmM8EmAOKQ6DFbXa4z62D9] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ogSYzsyGteyIoc2DXlAcMnmrVR6JJS3j] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5lEh891R2TzVENV3rtVy5CwdSK7QDIAN] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][s7QwFe0Zy5VmM8EmAOKQ6DFbXa4z62D9] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cMftmK1jJgpYFaOXn9VxHP1prPfIL4Ck] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][JZ0J1q4FJHW7txTJ5ekkBqVaivmPQov3] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3DRR1APzph1cqki4p7sj9pcibO4cG7ux] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ogSYzsyGteyIoc2DXlAcMnmrVR6JJS3j] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mXHs5gUNzJ90qrGuFHvQxL74T20hGpdt] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ZvIqgSydtaW3I4m9F8h1W6Vwaz61B8Ox] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ZvIqgSydtaW3I4m9F8h1W6Vwaz61B8Ox] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YId5A0utgkf79Mzzr1JBcuuonQdALDie] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][YId5A0utgkf79Mzzr1JBcuuonQdALDie] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][UjOcq1hsuttnyfNHLjCkPP8DjaYlvc4X] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][UjOcq1hsuttnyfNHLjCkPP8DjaYlvc4X] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][muPYZspOm4NMIQIMGMDZRYsBylKojlqU] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][muPYZspOm4NMIQIMGMDZRYsBylKojlqU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qy9oj1Hzvk389YA1IG9D8fRhJRiQAEcY] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][qy9oj1Hzvk389YA1IG9D8fRhJRiQAEcY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lAywMSuDR5INXbTv9obqrK3DDCDx1gwf] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JZ0J1q4FJHW7txTJ5ekkBqVaivmPQov3] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][9BHDJxHGvRKRFi7bD0oQMduJOryhk0P1] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][9BHDJxHGvRKRFi7bD0oQMduJOryhk0P1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][nWSK7nTTch86eS6BXO8ASSBY0Cw25auC] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][nWSK7nTTch86eS6BXO8ASSBY0Cw25auC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yPpUrF7hKbloLNePHbMPGRNG3S4GthZw] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][yPpUrF7hKbloLNePHbMPGRNG3S4GthZw] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][7XGXwKHmpUpydghnu7cIfXDSI6V13k3R] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][7XGXwKHmpUpydghnu7cIfXDSI6V13k3R] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xQtHvnpK0ffKMsafjpfUuAmKfca1DWiL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][3DRR1APzph1cqki4p7sj9pcibO4cG7ux] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zJiupBtKUldnso4BOk7U4BIgTfQ2neTx] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][zJiupBtKUldnso4BOk7U4BIgTfQ2neTx] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ta8b6t7JxDaAGobeLoQARaP6aOEAhi1p] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ta8b6t7JxDaAGobeLoQARaP6aOEAhi1p] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JFwKQwO4vDHn32y7zR6riX3T7ZXcC8s3] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][JFwKQwO4vDHn32y7zR6riX3T7ZXcC8s3] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yyVxQoepjRwa3xAbB88Q5X2IdNCeMaKQ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][yyVxQoepjRwa3xAbB88Q5X2IdNCeMaKQ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Y1B3Abw8lDvo5vRU2VH5jHsgzJF5coog] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][mXHs5gUNzJ90qrGuFHvQxL74T20hGpdt] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FwtfFbJ64GH6urCRkjYSIFG69mH6Kext] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][FwtfFbJ64GH6urCRkjYSIFG69mH6Kext] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Hh3C074Vhcy245rU5j5MSw0KKTLzDS4t] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Hh3C074Vhcy245rU5j5MSw0KKTLzDS4t] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][C0HLzgXwPiSWDuGlQWpAjmdC3g6V9KxU] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][C0HLzgXwPiSWDuGlQWpAjmdC3g6V9KxU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ebrzFq6OPn6uklLtWixGjZf31CMzwZfu] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ebrzFq6OPn6uklLtWixGjZf31CMzwZfu] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ieH4HS6MsCfWjTtq5wPyTDZUZoMrtcMn] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ieH4HS6MsCfWjTtq5wPyTDZUZoMrtcMn] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][xQtHvnpK0ffKMsafjpfUuAmKfca1DWiL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Y1B3Abw8lDvo5vRU2VH5jHsgzJF5coog] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][aWzxdm67a0MgCB2ZS7SSoqlGfq5kMk2e] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lAywMSuDR5INXbTv9obqrK3DDCDx1gwf] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ivR8VDISwd7ZU66TsFLnRuHeLqRWOw9M] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1Ejry7q7zgvfQPyvupEHKjzqKeBunisB] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ndBPbpDNL9OjUPQwxxsTPNbzUDl0kBCx] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][aWzxdm67a0MgCB2ZS7SSoqlGfq5kMk2e] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ivR8VDISwd7ZU66TsFLnRuHeLqRWOw9M] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][1Ejry7q7zgvfQPyvupEHKjzqKeBunisB] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][79JzodUYxUs6BvYBZAJEcPUVdMmtU2Fz] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ndBPbpDNL9OjUPQwxxsTPNbzUDl0kBCx] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sqfae789ydkfGm3wG8WIazfClwCu4c0c] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][f2o8m5lfMx3T5JMXYCmfsRvB4MLwK1Jr] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ZeskkjfAjonlfAe5QVnFJMJPJoFH1ffm] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][79JzodUYxUs6BvYBZAJEcPUVdMmtU2Fz] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][sqfae789ydkfGm3wG8WIazfClwCu4c0c] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][f2o8m5lfMx3T5JMXYCmfsRvB4MLwK1Jr] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mtM3HrdLTvn3hJA4BubaL6Da3eDyk9Lw] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ZeskkjfAjonlfAe5QVnFJMJPJoFH1ffm] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6sN2i07hNFsGeRhqHxDeeMBlD1pQ1wJC] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][okjuraZwK7FOLMZHVpL7qWdPeTrfn0EJ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][K2GnjER542DMjn8TOmHBlGfDtEtkqUyI] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][mtM3HrdLTvn3hJA4BubaL6Da3eDyk9Lw] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][6sN2i07hNFsGeRhqHxDeeMBlD1pQ1wJC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ENjeNnmnwTvsEBSA4aJVOuNvG7OkfAyk] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][okjuraZwK7FOLMZHVpL7qWdPeTrfn0EJ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][K2GnjER542DMjn8TOmHBlGfDtEtkqUyI] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][I3pBM62GztTlhHs15n2Z0gc3Q1bGGZZv] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mjRTv6xKlwjil7F9pcomdM8lKdcuT6zd] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DWvWC6pky22ZkEiPnfZYbB1QFvlawTGU] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ENjeNnmnwTvsEBSA4aJVOuNvG7OkfAyk] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][I3pBM62GztTlhHs15n2Z0gc3Q1bGGZZv] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][V1KGxRWsd08dUbWFvMMxewTYjDajoXOZ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mjRTv6xKlwjil7F9pcomdM8lKdcuT6zd] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DWvWC6pky22ZkEiPnfZYbB1QFvlawTGU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TapjRzsoUlGCxXj7PHq8NQrIeI57BhH9] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][z4FBMFOQHH2t3RIvpdiRMCFjKVErKIBG] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][LOz41KnfJr86YpbyQBshIiswEaWj8tYX] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][V1KGxRWsd08dUbWFvMMxewTYjDajoXOZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TapjRzsoUlGCxXj7PHq8NQrIeI57BhH9] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][z4FBMFOQHH2t3RIvpdiRMCFjKVErKIBG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ZeDBlBd3UCcY0xdKZou0mxc7NVjWKnar] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LOz41KnfJr86YpbyQBshIiswEaWj8tYX] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][rocg7imKhFgzJdJHDcZ124lSpEyvyaMc] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ruD4CL6mtqDPT0WeYocUXFtQjIwCqyfl] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][yjOXwafdwBm3BbH9m5TowNFRXYEVqafv] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ZeDBlBd3UCcY0xdKZou0mxc7NVjWKnar] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][rocg7imKhFgzJdJHDcZ124lSpEyvyaMc] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][l7CEM6CAdyzTwtu7Hzsb1wKGef9BW5d3] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][l7CEM6CAdyzTwtu7Hzsb1wKGef9BW5d3] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cDkppmh9wTKBJ8DmUWYXdV004e86QNjT] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][cDkppmh9wTKBJ8DmUWYXdV004e86QNjT] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][VF6xPYa6geaoNcoaBF0qn4aqyPQvmop8] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][VF6xPYa6geaoNcoaBF0qn4aqyPQvmop8] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][H4OwjNr4UBtpJHFZVjdZb1PwgjoyaL1C] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][H4OwjNr4UBtpJHFZVjdZb1PwgjoyaL1C] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mJqZtnnTUcgHvXV4gfiSna9zoymj33tS] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][mJqZtnnTUcgHvXV4gfiSna9zoymj33tS] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fyUfyZLCK0u6fZb1cJyvUsUAG8jxxqqQ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][fyUfyZLCK0u6fZb1cJyvUsUAG8jxxqqQ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][etMwcf96SJcVeWVYluGvE50kkJ8pFZF1] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][etMwcf96SJcVeWVYluGvE50kkJ8pFZF1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GG1Q0spi0MMAd9nHBuYBIQTZYLmYvrqp] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][GG1Q0spi0MMAd9nHBuYBIQTZYLmYvrqp] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Eqn6VHuPqfm9cTn9smZFdspuABxVhco8] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Eqn6VHuPqfm9cTn9smZFdspuABxVhco8] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JEeumifICquqUc4wnO1fSN4st724VvNo] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][JEeumifICquqUc4wnO1fSN4st724VvNo] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qKBwa9u5cR0SRzmyX8LirqT9VOduR7bl] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][qKBwa9u5cR0SRzmyX8LirqT9VOduR7bl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][x30N6ymxxQUDEaBnxAMItGMReHFNgFkr] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][x30N6ymxxQUDEaBnxAMItGMReHFNgFkr] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DErkiTsHGd3rFoZlq19P0mwuAjwyvxM1] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DErkiTsHGd3rFoZlq19P0mwuAjwyvxM1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DYo8E0OKmJzZz8URw2Vj6rhpdIuTsoGG] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DYo8E0OKmJzZz8URw2Vj6rhpdIuTsoGG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][P2jz5pk3eEtpuzU027Iqkcv6g04ohdeK] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][P2jz5pk3eEtpuzU027Iqkcv6g04ohdeK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][pFdhBxGP91rZmB9oxERu8FX5xstp3tMn] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][pFdhBxGP91rZmB9oxERu8FX5xstp3tMn] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][VhIXxt2VcbQAATczsm1A1aiu0Xy0ZQO9] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][VhIXxt2VcbQAATczsm1A1aiu0Xy0ZQO9] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][oe3dogHy3d0hDT7FdLrM0BkBsYNnygBq] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][oe3dogHy3d0hDT7FdLrM0BkBsYNnygBq] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][pU7K4SSL8Yqr93N3AdGcDr1SnwdpBMLO] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][pU7K4SSL8Yqr93N3AdGcDr1SnwdpBMLO] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4TMzWzvt8UyX2mulacUrjimESgo1JgdG] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][4TMzWzvt8UyX2mulacUrjimESgo1JgdG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][S96XsKMngPhlTK9I32pnHNAsXQtLN70O] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][S96XsKMngPhlTK9I32pnHNAsXQtLN70O] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][EruVf00ORVkWRIldFRUa53AeqTVcawoM] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][EruVf00ORVkWRIldFRUa53AeqTVcawoM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cYwEbq072XwAQOioZ20a1sCCOp1lAnUH] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][cYwEbq072XwAQOioZ20a1sCCOp1lAnUH] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DnX6zk5o15CkXDziQhBIby3xOyFJOIvn] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DnX6zk5o15CkXDziQhBIby3xOyFJOIvn] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2JHs7xaI1K9rVwCbcXNHf2DceIlORdmw] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][2JHs7xaI1K9rVwCbcXNHf2DceIlORdmw] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][XzQVOWyZEdEXXYY2Mlomlh91eP3WDfJt] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][XzQVOWyZEdEXXYY2Mlomlh91eP3WDfJt] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DaVGt3V3lTLXO1YGWUrU2GvFopIvugCY] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DaVGt3V3lTLXO1YGWUrU2GvFopIvugCY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][HtMqxzGFTEvkl5MoiFKaTudSvbmYXwPE] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][HtMqxzGFTEvkl5MoiFKaTudSvbmYXwPE] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JBkxdCSwToRHKJLFx2uWiAv2u0pyjVf0] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ruD4CL6mtqDPT0WeYocUXFtQjIwCqyfl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0WgEZaboJEloaFRwUXbwlULz8ccj4ckY] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][0WgEZaboJEloaFRwUXbwlULz8ccj4ckY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][UlpUZAXQWumO2F7PMLTK8OlpwM2ihlHj] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][UlpUZAXQWumO2F7PMLTK8OlpwM2ihlHj] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hGwL2u86F5164zP0EoTZxemW1Mhzzs1z] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][hGwL2u86F5164zP0EoTZxemW1Mhzzs1z] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eHx2J25tuRvexMWcYoXtetX2MeuPRCrK] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][eHx2J25tuRvexMWcYoXtetX2MeuPRCrK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DT0H3ONKGkmPHSMKLixkX959xy94O2Ys] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DT0H3ONKGkmPHSMKLixkX959xy94O2Ys] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][thmWBKuF05QB4s9yiESZwsmkvMIYQ30u] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][thmWBKuF05QB4s9yiESZwsmkvMIYQ30u] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][EvA5d3NHsis5GhOrbmfDKgrYEezkPrJQ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][EvA5d3NHsis5GhOrbmfDKgrYEezkPrJQ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YMkzFhWeyNRdqwml6JnjJI80LQ0R9PZU] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][YMkzFhWeyNRdqwml6JnjJI80LQ0R9PZU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0kvec5X6UjFHHXIJvca8zXpyUDfbfK3e] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][0kvec5X6UjFHHXIJvca8zXpyUDfbfK3e] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][97lNE2kAnncUlD4sZYjPTTagWGpDM6i9] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][97lNE2kAnncUlD4sZYjPTTagWGpDM6i9] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hPY40jLc5exfm59EQB5OGJim2dydonpE] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][hPY40jLc5exfm59EQB5OGJim2dydonpE] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][kgnMrOZxHzbPk4deBr3nN4HGpwC2XojG] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][kgnMrOZxHzbPk4deBr3nN4HGpwC2XojG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MGteuTtnb02MCE4qMw8X11Xow8vuaS63] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][MGteuTtnb02MCE4qMw8X11Xow8vuaS63] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][rSWOEf2lPZvA5IAMnEPOYJ7JovIfoDSH] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][rSWOEf2lPZvA5IAMnEPOYJ7JovIfoDSH] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][yjOXwafdwBm3BbH9m5TowNFRXYEVqafv] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lJHjPn4gf2uASqMwARQVKPndYDK2GH0J] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][lJHjPn4gf2uASqMwARQVKPndYDK2GH0J] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][SEIY0xSxAGqgcPxMm86Lxn0YDFRVG2rF] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][SEIY0xSxAGqgcPxMm86Lxn0YDFRVG2rF] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][7Qoy71k6kiKC1Td0eNLWwnSed8Deutp1] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][7Qoy71k6kiKC1Td0eNLWwnSed8Deutp1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][9q0Xn5U2Xyu50xDR6R0gsVhFJbAZfwZl] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][9q0Xn5U2Xyu50xDR6R0gsVhFJbAZfwZl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ZpxLP61mpkvG14fp9tWvXgvOIQtaF6qY] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ZpxLP61mpkvG14fp9tWvXgvOIQtaF6qY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][llghDBmXs4nUeAMbEh2w6RaLJrbFRKBo] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][llghDBmXs4nUeAMbEh2w6RaLJrbFRKBo] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TN7PkTWW2wd9OMZpDvwM1WPEJnuBoz0I] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][TN7PkTWW2wd9OMZpDvwM1WPEJnuBoz0I] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][oPQVyv1paTVOIQUFyMbbswJ9q4CDTybI] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][oPQVyv1paTVOIQUFyMbbswJ9q4CDTybI] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][W0F560echC9cjS1jEgP9j4W2KiR9FhYb] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][W0F560echC9cjS1jEgP9j4W2KiR9FhYb] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eMCeIkFBZ85QXSrqYbd2j64oTtT1EIK0] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][eMCeIkFBZ85QXSrqYbd2j64oTtT1EIK0] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0xUICTp9Vn8JvqWGiXz98zDki4HtCyiB] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][0xUICTp9Vn8JvqWGiXz98zDki4HtCyiB] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GlLwN7ysFoysTulQSI4htlZxmHzkIUUc] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][GlLwN7ysFoysTulQSI4htlZxmHzkIUUc] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ddqZT28Jfc4GywpAGHz6UO6V4DjsSiLi] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ddqZT28Jfc4GywpAGHz6UO6V4DjsSiLi] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][frlJSrrZjBnvN4Bf0oOYb3sIYg2qAhMM] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][frlJSrrZjBnvN4Bf0oOYb3sIYg2qAhMM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IbUEoVl5ZOVQLvMzu00MvMnytegOu2TK] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][0wCJ20xKdJ18hwfzdSp3G7kY9KT5aZTG] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][BSA4cVdQLcB0cdwwM8js4ofigAXsf1jV] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JBkxdCSwToRHKJLFx2uWiAv2u0pyjVf0] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][0wCJ20xKdJ18hwfzdSp3G7kY9KT5aZTG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IbUEoVl5ZOVQLvMzu00MvMnytegOu2TK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TyGsBULVdPK3IWEIoYWJSv3iWdbiUZKM] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][BSA4cVdQLcB0cdwwM8js4ofigAXsf1jV] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][S3vvoTIzE3TEZoh6afQxXh6toKmlbg6G] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][oFyJ59ixp0tna9hZz2doPnNGGBuZTQCp] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ZyPcPut6GW9Ey8Fzl5Z93DFu9629LLZB] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][TyGsBULVdPK3IWEIoYWJSv3iWdbiUZKM] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][S3vvoTIzE3TEZoh6afQxXh6toKmlbg6G] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][oFyJ59ixp0tna9hZz2doPnNGGBuZTQCp] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ZyPcPut6GW9Ey8Fzl5Z93DFu9629LLZB] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JRgOGSadxUqsqrQtOBmNPrlzqmH54BC3] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][rtGmQXwETECozKlpDD6djAtf0P2IzuJ9] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bhSshCNU4CFv2uAe7BFAelLiMqmrdeh1] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wJpHr3oGjeZ1ioPgU7bCvlXrag0JD634] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JRgOGSadxUqsqrQtOBmNPrlzqmH54BC3] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][rtGmQXwETECozKlpDD6djAtf0P2IzuJ9] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Q05VbZhI2M69lakyCRcy8AxBoLMdwIr9] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wJpHr3oGjeZ1ioPgU7bCvlXrag0JD634] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bhSshCNU4CFv2uAe7BFAelLiMqmrdeh1] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][LYmJWQ5x6QNpBcphJR24dM9qpCWf51ho] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vyBQMZGTV2U2LAI5SvevjYEKi6n1kHQ4] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][SfG8Ynz5usiZmQEs2C2dP3bA7P9Oiwjy] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Q05VbZhI2M69lakyCRcy8AxBoLMdwIr9] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][zHwRzsRrvWT3KnXjHPCgPUD5tthX7EzJ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LYmJWQ5x6QNpBcphJR24dM9qpCWf51ho] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vyBQMZGTV2U2LAI5SvevjYEKi6n1kHQ4] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][SfG8Ynz5usiZmQEs2C2dP3bA7P9Oiwjy] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][qdyI2Sgk7fGqFL7k5Hr48NIXoMjPDwlJ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][QeCJ7aFCQsmnaaDycuflNVyKBsUjUJVL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][kmMk2tA6kTi6MyKT3Vje6RbjWxpPYKsk] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zHwRzsRrvWT3KnXjHPCgPUD5tthX7EzJ] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][qdyI2Sgk7fGqFL7k5Hr48NIXoMjPDwlJ] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][QeCJ7aFCQsmnaaDycuflNVyKBsUjUJVL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][t9x8dWUTcsYzKT72b1iOU9DIuh3PXlwv] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][kmMk2tA6kTi6MyKT3Vje6RbjWxpPYKsk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Zf5xQb8Ens55taCJFBAnElGbMyOXSHNT] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ttVHvl8DReR5kMQXSIPMhppyjSVB9ySp] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][t9x8dWUTcsYzKT72b1iOU9DIuh3PXlwv] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][n43jV2MTd2phTmhlRqVqInr64pqsmqCs] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ttVHvl8DReR5kMQXSIPMhppyjSVB9ySp] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Zf5xQb8Ens55taCJFBAnElGbMyOXSHNT] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Y06Lsne5m7kYyaqCT7q7hGv0IHZVtMr1] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][iGGaMRrM6P4KkvxWEmI0LfLCBONW5LpU] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ayhI82vrUhjkI1ybBugBXB7TyHVtavi9] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][n43jV2MTd2phTmhlRqVqInr64pqsmqCs] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Y06Lsne5m7kYyaqCT7q7hGv0IHZVtMr1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][H9Zhe2VltDGuu6SGSpFfbz6RpJLX0nUG] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][iGGaMRrM6P4KkvxWEmI0LfLCBONW5LpU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ayhI82vrUhjkI1ybBugBXB7TyHVtavi9] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Asy3Tiy1WPtucgapjidHoub6h5g5HqmS] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][xKBZQ8Hc42hUTpjYrwSwj7gQCDo1FDSn] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YleAGBaE8831X8c3ROoUwHC15RsE6N64] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][H9Zhe2VltDGuu6SGSpFfbz6RpJLX0nUG] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Asy3Tiy1WPtucgapjidHoub6h5g5HqmS] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fM9THBkF128vbZVxUgMwYbqe8eruZpFx] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xKBZQ8Hc42hUTpjYrwSwj7gQCDo1FDSn] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YleAGBaE8831X8c3ROoUwHC15RsE6N64] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ABSuXZT8pfaXIX1Z5lQLBv9FbNcNsa44] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][wjr06wl7f00pdZ5TBepo4GQDLvaUi8KE] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][c2bQC3C7zDaxaW5vQr3f8JpTrquDh10c] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][fM9THBkF128vbZVxUgMwYbqe8eruZpFx] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ABSuXZT8pfaXIX1Z5lQLBv9FbNcNsa44] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LHdxS89gDOTMko5qRE4CFCkygRQymflh] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][c2bQC3C7zDaxaW5vQr3f8JpTrquDh10c] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wjr06wl7f00pdZ5TBepo4GQDLvaUi8KE] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JEwVc0jcetx7JerLvHDGjn0UkkWKxMPw] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][KOARbN5H4Eji4f4mYcQ5asGA7g6iKsOa] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][75pbBjtDLjv2KTauip1FikgUuJcAx30M] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LHdxS89gDOTMko5qRE4CFCkygRQymflh] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JEwVc0jcetx7JerLvHDGjn0UkkWKxMPw] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][lodzulXgCryE6ahCsP48uYrvYW0RNMsS] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][75pbBjtDLjv2KTauip1FikgUuJcAx30M] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KOARbN5H4Eji4f4mYcQ5asGA7g6iKsOa] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WE22n6jlTDGUtVvRL6QrrPHuzO5ZxtVe] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][JmzScgV8gRgFmQ5eYLYSa1GwHIGJPM42] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][lodzulXgCryE6ahCsP48uYrvYW0RNMsS] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Nd7NzaX2sLSeKcn036fko9D5uC6bXTwH] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][WE22n6jlTDGUtVvRL6QrrPHuzO5ZxtVe] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][y5tzDgWMbWHtpRMduAJsdy5kBpjy93dl] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JmzScgV8gRgFmQ5eYLYSa1GwHIGJPM42] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Nd7NzaX2sLSeKcn036fko9D5uC6bXTwH] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mhGfZ2FGMKu6kQ89gtsfkIVwejcm2Ukz] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][5grAeIG3pXkokzqb2ckNDZyz7XLxWtJB] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][oj3KxSkHvBomo8A2tyi2Lln03igrmO4l] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][y5tzDgWMbWHtpRMduAJsdy5kBpjy93dl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mhGfZ2FGMKu6kQ89gtsfkIVwejcm2Ukz] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][tmUZSAxdyruSHv9cOM5LecM4oQPrI7S6] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][oj3KxSkHvBomo8A2tyi2Lln03igrmO4l] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5grAeIG3pXkokzqb2ckNDZyz7XLxWtJB] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][rqrLe1U6pQV7jRUvntTBCvxVMZTYMwZT] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][tmUZSAxdyruSHv9cOM5LecM4oQPrI7S6] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lqtxWJEZObQt5n6Mo5pSJHsnxSA5mdUL] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][NEcXnnbOY0C01Dv9L35YVYJk9Aj3gqex] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][rqrLe1U6pQV7jRUvntTBCvxVMZTYMwZT] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][aVjRSwbmTCKD7cxJcxUAtVSxEtTLvkti] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][EOaXnLZ2CWTA5P6XVVWpyej9OuPGgeBt] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][lqtxWJEZObQt5n6Mo5pSJHsnxSA5mdUL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][NEcXnnbOY0C01Dv9L35YVYJk9Aj3gqex] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][aVjRSwbmTCKD7cxJcxUAtVSxEtTLvkti] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zSGQa8pi5CFMtWD0f4mczEUgn6vdj7wS] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][BaOhQIKs9GP0Y6LWIIzBu4JKzPLP9K5K] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][EOaXnLZ2CWTA5P6XVVWpyej9OuPGgeBt] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][VG276KU9mBeGYejATsYumNPzdIosGI5Z] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DIPHGFfyK8YvYIV0IHrWFASXymQkVRln] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zSGQa8pi5CFMtWD0f4mczEUgn6vdj7wS] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][BaOhQIKs9GP0Y6LWIIzBu4JKzPLP9K5K] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][2KufZMeac011QTo8qZ5yNU1cJKbTxpyK] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][VG276KU9mBeGYejATsYumNPzdIosGI5Z] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][VhP3JRARvu4dXcZZe2H0aqN82L7nWUCU] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DIPHGFfyK8YvYIV0IHrWFASXymQkVRln] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][BexPOZRGzO7J2X5AKpAHqWhUz0jq9K52] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][2KufZMeac011QTo8qZ5yNU1cJKbTxpyK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YeeM2oDJIjv2D5IOSrMS6KRTlQLOL0Nt] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][VhP3JRARvu4dXcZZe2H0aqN82L7nWUCU] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][4SM4ry1rbQ1ayjdVcp38AFVMTBXXVpHy] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][BexPOZRGzO7J2X5AKpAHqWhUz0jq9K52] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1KNx5wSY1vtqOXRt9Gjm3PrnDjk2vk3I] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YeeM2oDJIjv2D5IOSrMS6KRTlQLOL0Nt] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][OYJphQhy8CmzvRduHuN4pKWHktl4TB6i] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Y3fk2bnGCpHABlbzts2ODoQq6bP9Q9NS] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4SM4ry1rbQ1ayjdVcp38AFVMTBXXVpHy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1KNx5wSY1vtqOXRt9Gjm3PrnDjk2vk3I] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][OYJphQhy8CmzvRduHuN4pKWHktl4TB6i] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hgv2HahNABbPGoa4aaJ2LQ83PmwkkINv] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][hgv2HahNABbPGoa4aaJ2LQ83PmwkkINv] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IxOzoWvEZTgXYXz0K4mHQK5caRxKLCJZ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][IxOzoWvEZTgXYXz0K4mHQK5caRxKLCJZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Efr2lDBVdg1fBh1hSyn1qCs8YaKnoOtW] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Efr2lDBVdg1fBh1hSyn1qCs8YaKnoOtW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][BoKC5nshhrJHuNb1sbCZdeg9RSR10bRi] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][BoKC5nshhrJHuNb1sbCZdeg9RSR10bRi] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yr3NZ1cq9UunAQQ5YUg5aqf0v6eAN1CW] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][yr3NZ1cq9UunAQQ5YUg5aqf0v6eAN1CW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yYb201vlxMc7gFeyXAFo94Q8ezO9jiLg] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][yYb201vlxMc7gFeyXAFo94Q8ezO9jiLg] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3z0n1faXYQjKCO85i7ZpTu261xPuy21c] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][3z0n1faXYQjKCO85i7ZpTu261xPuy21c] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Y3fk2bnGCpHABlbzts2ODoQq6bP9Q9NS] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ht5LqxnGdnFfG0DI2D4KRVyiERwEWXOR] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ht5LqxnGdnFfG0DI2D4KRVyiERwEWXOR] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sWTTEYadZnfWYtmXVOX5GK5wlNGsHlFw] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][sWTTEYadZnfWYtmXVOX5GK5wlNGsHlFw] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][NIIR2FDhOeZxaus6t6JtQV020eoHfisg] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][NIIR2FDhOeZxaus6t6JtQV020eoHfisg] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fFotnN8SrcG7o1IgFrJwHuP9Ppmz6QiV] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][fFotnN8SrcG7o1IgFrJwHuP9Ppmz6QiV] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][s3WdcKhgp6PgvX9a7fCHAFBwq8DzZ7qU] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][s3WdcKhgp6PgvX9a7fCHAFBwq8DzZ7qU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][i4kWB01hOu8dG7VPG2RLi6UToSI1sswE] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][i4kWB01hOu8dG7VPG2RLi6UToSI1sswE] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][F2HCM0tESZG9JfLnGXZolmxGzAIGgbhg] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][F2HCM0tESZG9JfLnGXZolmxGzAIGgbhg] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][kqcLQdqbwmMK6REy0IytO8URKGtJj37T] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][kqcLQdqbwmMK6REy0IytO8URKGtJj37T] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8tFlsFiIJxZfx5lWjvg59V828Uq1Vu92] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][8tFlsFiIJxZfx5lWjvg59V828Uq1Vu92] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qAu0OVZzCD9JEFR3X5b5vp5RFedGAcRD] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][qAu0OVZzCD9JEFR3X5b5vp5RFedGAcRD] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CjM1ZUmEdkJsaN5eV0f5a26BBUIwxqVM] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][CjM1ZUmEdkJsaN5eV0f5a26BBUIwxqVM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PrcSYj9Z3MDg7kSklvuDm86xD3tYSq8q] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][PrcSYj9Z3MDg7kSklvuDm86xD3tYSq8q] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][rZzkhjrXVlm35d4Rl3s2uNYb0g2lELO9] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][rZzkhjrXVlm35d4Rl3s2uNYb0g2lELO9] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][dpX73Dhn0DFtOzvPB1fNbOkndC4rinoM] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][dpX73Dhn0DFtOzvPB1fNbOkndC4rinoM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][v63M5qPAmIbhRt2MxeDoesOXZAwuT0rx] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][v63M5qPAmIbhRt2MxeDoesOXZAwuT0rx] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Iq9QN8oeGBeiENRjNyXrsX8S8oVFn4rL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Iq9QN8oeGBeiENRjNyXrsX8S8oVFn4rL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][aIrZhEFQ57dhab7N96J3s7lt1bHdr84C] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][aIrZhEFQ57dhab7N96J3s7lt1bHdr84C] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][dccee9fqkLSLJEJXCRseajXyPiTQgQcF] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][dccee9fqkLSLJEJXCRseajXyPiTQgQcF] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bMSSBGi1eCMNuI7Drkh01kH0hxuCPv1t] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][bMSSBGi1eCMNuI7Drkh01kH0hxuCPv1t] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3JAF2PjH2MdgzXLYyIHPCwG3TyAmFGKD] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][3JAF2PjH2MdgzXLYyIHPCwG3TyAmFGKD] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][dlrwyqI6cfTaaGCGnWtY4dAfzNxbs2B3] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][dlrwyqI6cfTaaGCGnWtY4dAfzNxbs2B3] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][05SubQ7YC14WYE4BAmR9xel6Mo6z9DKw] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][05SubQ7YC14WYE4BAmR9xel6Mo6z9DKw] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TQpvpaA0aKcWXoFJT8xziuhcvwkDP6nh] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][bbKeIV5aPnGxbWRosQAWmFTjH9LseEEx] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CEC2ZOaGmSCjYhicgQAz296wH0MxUcyX] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TQpvpaA0aKcWXoFJT8xziuhcvwkDP6nh] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][QNX0tXoJimpZ4hjqzzHRdLN3LI9LSkla] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ZX3EGhKYzZ06s8Ul90udlkMLJZRGSiQl] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bbKeIV5aPnGxbWRosQAWmFTjH9LseEEx] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][QNX0tXoJimpZ4hjqzzHRdLN3LI9LSkla] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CEC2ZOaGmSCjYhicgQAz296wH0MxUcyX] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][MqQQCxzrL9lerwegQvwHQKTPSPu1bL5f] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sek3w7zGFliB7yWNkDz0nUiNTlU5XGBy] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bM4tKwZAzY3BQut5Xwe1K9yC3nWiVs2v] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ZX3EGhKYzZ06s8Ul90udlkMLJZRGSiQl] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Rhl5rLvjaSnWxY1DBbLRR9UOVfcTwMYc] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MqQQCxzrL9lerwegQvwHQKTPSPu1bL5f] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sek3w7zGFliB7yWNkDz0nUiNTlU5XGBy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bM4tKwZAzY3BQut5Xwe1K9yC3nWiVs2v] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][p9vFg0PM86222VqDK7SVluj6zMYYvSYA] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wn2vKb66YtxJiQhY3wCrHejyd5tU5eIG] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Rhl5rLvjaSnWxY1DBbLRR9UOVfcTwMYc] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][qNlMW8aAtF1pTbbmiBJQnqKRPno6H3X4] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][T1VYOts3izRDNmYSMjAQL6PglJfUYRtQ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][p9vFg0PM86222VqDK7SVluj6zMYYvSYA] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][r12YbVLHIWtCykLREdsnmG3MtPwIH43Y] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][r12YbVLHIWtCykLREdsnmG3MtPwIH43Y] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ugmLilUTzvgJ2CgCZu6k3zLEmqJdf0uT] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ugmLilUTzvgJ2CgCZu6k3zLEmqJdf0uT] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cEUiyrJElQIwgcm4qJ9BBBPyncGzHX93] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][cEUiyrJElQIwgcm4qJ9BBBPyncGzHX93] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5mG8ShczC9SGU4jHo5seJcyJsMnLMNm1] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][5mG8ShczC9SGU4jHo5seJcyJsMnLMNm1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hoD1Ti5O7Fv7bRotJBILIyZUrXm2KkWy] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wn2vKb66YtxJiQhY3wCrHejyd5tU5eIG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TsKq010GdXMp1d3DJtg8AE70njdwp5aB] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][TsKq010GdXMp1d3DJtg8AE70njdwp5aB] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2VOYi9wa1qoSdllumcV7RFZRDj8S0d2S] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][2VOYi9wa1qoSdllumcV7RFZRDj8S0d2S] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][uuZ2N1MtXXjloPxmgWBN54NK31zKSkPg] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][uuZ2N1MtXXjloPxmgWBN54NK31zKSkPg] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KdZQw4IJvL7iJe0fHxTnv9mjGDr7HeU4] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][KdZQw4IJvL7iJe0fHxTnv9mjGDr7HeU4] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][qNlMW8aAtF1pTbbmiBJQnqKRPno6H3X4] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][EfAfJuNuOuKGjtOlm8q6nswiMz0HL5hh] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][EfAfJuNuOuKGjtOlm8q6nswiMz0HL5hh] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CeldXeaNVszZqdTAenkpt9wFtALFBwZL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][CeldXeaNVszZqdTAenkpt9wFtALFBwZL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0jaBNpUbZwemuWrBx83822Fl0XJAnisj] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][0jaBNpUbZwemuWrBx83822Fl0XJAnisj] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hlClevp7vU0d7pweRpUHtFZAnPKaAkVH] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][hlClevp7vU0d7pweRpUHtFZAnPKaAkVH] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PdzCQwbRkmpOADi2Y6BAz39NSv3HNRKm] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][T1VYOts3izRDNmYSMjAQL6PglJfUYRtQ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ztJXmOoWb7Eh6UozCuK1F9if0urRWDt4] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ztJXmOoWb7Eh6UozCuK1F9if0urRWDt4] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][afM2BvkSr85ksAoLH0Ev3o1OgwjzLo18] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][afM2BvkSr85ksAoLH0Ev3o1OgwjzLo18] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][dOqzfYxWUWDEuazdtNYpjry0JjGK5iVl] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][dOqzfYxWUWDEuazdtNYpjry0JjGK5iVl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1TJjAtfR3fv8Ts4Z74yHFPFT0fhVzCRm] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][1TJjAtfR3fv8Ts4Z74yHFPFT0fhVzCRm] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4HlX172Kr6bdwXnLupCdjzOoQ3Dc2zIO] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][lUMnDXsRANT51QFCVTr2qlyfwY4rHYP5] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hoD1Ti5O7Fv7bRotJBILIyZUrXm2KkWy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4HlX172Kr6bdwXnLupCdjzOoQ3Dc2zIO] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PdzCQwbRkmpOADi2Y6BAz39NSv3HNRKm] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][rXAz3xyDwahVx6XCavqK4t6pYDxs7xNj] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lUMnDXsRANT51QFCVTr2qlyfwY4rHYP5] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4MDAKrs3WWNrTx51BL6se8HVyOn5mJO1] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qT7BMLby5VosNjnlNlA3ZmjARqyW7iYO] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][PVQOcFL8u8ZL67aZCwkRo0xLldAa2zeG] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][rXAz3xyDwahVx6XCavqK4t6pYDxs7xNj] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4MDAKrs3WWNrTx51BL6se8HVyOn5mJO1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qT7BMLby5VosNjnlNlA3ZmjARqyW7iYO] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][eZ2mfjTtWv90dKy1MO4ag5Y5etmiH5oC] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PVQOcFL8u8ZL67aZCwkRo0xLldAa2zeG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6MHadi6z3Jvxn7VhXbMSxu38wgDNIlbT] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fBf73eOd7yIfKulWoY04RzRHZdkUSDVl] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][emjxWssAp8GtO35MOBjiKwjcTJdb7Znj] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eZ2mfjTtWv90dKy1MO4ag5Y5etmiH5oC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fBf73eOd7yIfKulWoY04RzRHZdkUSDVl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6MHadi6z3Jvxn7VhXbMSxu38wgDNIlbT] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][gVCjl91J03521XT0W832dv8tZLQDP5q5] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Sa2UoTEWG3FZiaN4c3uINM6IzGczdw5J] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][emjxWssAp8GtO35MOBjiKwjcTJdb7Znj] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][RkobCy0SXf31IC4TUzOmyyhcJT8AFkSw] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][B5CyoevQ1uZA8Ndfd6BVEN7RgVaPayIu] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][gVCjl91J03521XT0W832dv8tZLQDP5q5] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Sa2UoTEWG3FZiaN4c3uINM6IzGczdw5J] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][RkobCy0SXf31IC4TUzOmyyhcJT8AFkSw] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][7LzRRcF1TWQP1qLeqj0tchHEZgo98Yl6] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Yw8G2f0NtW313SKMl8CjHl9e0yR5Nzzy] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mHnxjP5lE738xAWueRB7MToQh1MN38Ze] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][B5CyoevQ1uZA8Ndfd6BVEN7RgVaPayIu] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][7LzRRcF1TWQP1qLeqj0tchHEZgo98Yl6] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MTrpPmc85CJxEc2RsCiEPeivjXmJAPrA] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Yw8G2f0NtW313SKMl8CjHl9e0yR5Nzzy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mHnxjP5lE738xAWueRB7MToQh1MN38Ze] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][AnALgD7Yo9jNUzM0SOY3O3DJew9abfWj] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][rfLhLwQAPf3Z4Dt6dJHeoul0bRUgB2ub] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][iYvgjgXyO3rELpDQPDo5tGFpuhMcJu3L] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MTrpPmc85CJxEc2RsCiEPeivjXmJAPrA] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][AnALgD7Yo9jNUzM0SOY3O3DJew9abfWj] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][rfLhLwQAPf3Z4Dt6dJHeoul0bRUgB2ub] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GLvXc3tRNTknQcwM1ikJLrfKzuD7Jcb3] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][iYvgjgXyO3rELpDQPDo5tGFpuhMcJu3L] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][AJo1syc6dzA6UiNaz2cQVkRE3QwtkfTI] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][uuMNRnpNqJHA4mHSExIaKMLk8DLANja4] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][U81kYeHnXBqirjjzu1EN05m7cESW51HZ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GLvXc3tRNTknQcwM1ikJLrfKzuD7Jcb3] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][AJo1syc6dzA6UiNaz2cQVkRE3QwtkfTI] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][uuMNRnpNqJHA4mHSExIaKMLk8DLANja4] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CrmxUbL6yJDa4dwGXUIBKjasAVslOaiv] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LHs7gg1kTk3WUe3Fy5K4imA0GuAPhJ2b] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][U81kYeHnXBqirjjzu1EN05m7cESW51HZ] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][vAjt2sexvXRJxHbOd1cYeHc0Zhv7OKuZ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GWuXE5SVPK4b7rzdKYy2Pm2NP5SKpkxU] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CrmxUbL6yJDa4dwGXUIBKjasAVslOaiv] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LHs7gg1kTk3WUe3Fy5K4imA0GuAPhJ2b] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][r0Fv7mQumzghoGrSFTInFXl1o43gmU0D] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5rDVtRzFRhLaXJRIU9scnBWhcYPwyzce] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vAjt2sexvXRJxHbOd1cYeHc0Zhv7OKuZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GWuXE5SVPK4b7rzdKYy2Pm2NP5SKpkxU] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][3NXftfApOQRHtIt3jQLUIZc2JvtZzuGn] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][r0Fv7mQumzghoGrSFTInFXl1o43gmU0D] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cJl7XUGUT8nPcS78mzMCipdKvNo57CLt] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5rDVtRzFRhLaXJRIU9scnBWhcYPwyzce] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][mWk3kOQs50sQ3LpA4NJdWRajHRIPUBPg] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][dkqSp83RTEmCA3UzUKY2i1BHIC7BLQwk] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3NXftfApOQRHtIt3jQLUIZc2JvtZzuGn] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cJl7XUGUT8nPcS78mzMCipdKvNo57CLt] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][QjFDrxgKPenHi36fxABRLBQPiM5MgSke] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][R5aL3fS9C1YEYCe96U8RdmisQ3c8X5IM] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][mWk3kOQs50sQ3LpA4NJdWRajHRIPUBPg] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][dkqSp83RTEmCA3UzUKY2i1BHIC7BLQwk] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][QjFDrxgKPenHi36fxABRLBQPiM5MgSke] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][jqoZ9vY3SVnXXhf6vCFJCdendTkW6EAf] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][HoppmyamGjsOJHTRYkVVSNM5tUfISfXp] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][R5aL3fS9C1YEYCe96U8RdmisQ3c8X5IM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Rhxyxk1g8wEVJWA6GF2TfTnKpgdUN36p] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][b9ux6ei9aGvmceKQtkcFcSO5mymeZDDZ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][HoppmyamGjsOJHTRYkVVSNM5tUfISfXp] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][jqoZ9vY3SVnXXhf6vCFJCdendTkW6EAf] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Rhxyxk1g8wEVJWA6GF2TfTnKpgdUN36p] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][b9ux6ei9aGvmceKQtkcFcSO5mymeZDDZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][h7vtc8rV6FrciXRaB5SrXjUCxfFgml8I] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2mIqqHXq4kYOfS9dEq7mPLix7OPrQcj4] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][A72NmXF4m0TfXRWxREpplXx8HBf8HYSx] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][QzFsrIiRoRjgz37fKr9A8MSuSCM3Lk2L] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][2mIqqHXq4kYOfS9dEq7mPLix7OPrQcj4] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][h7vtc8rV6FrciXRaB5SrXjUCxfFgml8I] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][A72NmXF4m0TfXRWxREpplXx8HBf8HYSx] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][QzFsrIiRoRjgz37fKr9A8MSuSCM3Lk2L] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][odpKVRWYAqVfd30B8gD6K1J8MVzMDTf7] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OpeDV5jHFmz8sKiIjNGOOWwJwZw7tgJa] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][HryaHKX8wKiVe7azTmrTx4fegqUHwjGy] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KWMOpzxZlzpPHYgEV9PpEA6P0AhM2gld] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][odpKVRWYAqVfd30B8gD6K1J8MVzMDTf7] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OpeDV5jHFmz8sKiIjNGOOWwJwZw7tgJa] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][HryaHKX8wKiVe7azTmrTx4fegqUHwjGy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][862RwYIVhiDEQDdOCVr4YnCD0envz82l] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GMTpXpXuhlnGUOhg91tpNrPtxSDUjYGw] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KWMOpzxZlzpPHYgEV9PpEA6P0AhM2gld] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ec4inF2eO4mo5EeCZRZu2QpW4WlbM2VD] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][mtqpzpMozikj2yVb17cIVwSX6nOTkCgH] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GMTpXpXuhlnGUOhg91tpNrPtxSDUjYGw] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][862RwYIVhiDEQDdOCVr4YnCD0envz82l] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ec4inF2eO4mo5EeCZRZu2QpW4WlbM2VD] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][CkFkaDjBvVUCUohtWYwSoGIu74t2dhG5] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CVEZXyx5r0mjTZcN70fG1fWld4xhQQKM] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mtqpzpMozikj2yVb17cIVwSX6nOTkCgH] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WC9y45r81E6YxJjLh5Nlh8UYqfpYawic] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][IGVQPva6A4xmIR7vF47p6BXtV9uDKDxG] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CkFkaDjBvVUCUohtWYwSoGIu74t2dhG5] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CVEZXyx5r0mjTZcN70fG1fWld4xhQQKM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WC9y45r81E6YxJjLh5Nlh8UYqfpYawic] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][2bVBZykKahplYY3kY6h9LlYX5eAtfQkw] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YYSZX9rJhMb9Allqe13fe5Ajpy97Excm] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cUMqgvDuOuCL8uKCK4ixftK3aFtFm1Dd] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IGVQPva6A4xmIR7vF47p6BXtV9uDKDxG] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][LS9EQr3WajKYXxGX9imZJEkyCtckWzT9] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2bVBZykKahplYY3kY6h9LlYX5eAtfQkw] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YYSZX9rJhMb9Allqe13fe5Ajpy97Excm] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cUMqgvDuOuCL8uKCK4ixftK3aFtFm1Dd] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Kg8nuQtrx4v01a5YPYM2Iwa3GVtort61] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wX8lsz9COCHa1rdPhc7r9rBk36U93l6l] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IQgWdyXLAEUsomkXrGvS0u42pWsdD1gi] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LS9EQr3WajKYXxGX9imZJEkyCtckWzT9] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Kg8nuQtrx4v01a5YPYM2Iwa3GVtort61] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mU0zyChOMyY95H5FD2ItUI8Us5vF6NGk] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wX8lsz9COCHa1rdPhc7r9rBk36U93l6l] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IQgWdyXLAEUsomkXrGvS0u42pWsdD1gi] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][GelH3zr8y49E6btUQ2r46N4fIqrHdoQv] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][7ypgqy96gq34DJxTiOzVBxLMfeKBKf32] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mU0zyChOMyY95H5FD2ItUI8Us5vF6NGk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][595MDkDgyd79GQYWeXjsx3eacm3r3DOW] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][p4bbsEoCIhv7kc1GkU0RhJbaxw7ePLFG] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][7ypgqy96gq34DJxTiOzVBxLMfeKBKf32] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GelH3zr8y49E6btUQ2r46N4fIqrHdoQv] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][595MDkDgyd79GQYWeXjsx3eacm3r3DOW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fwuenwN8QXn4OgCdYjiwFF8qNQRXlwap] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][fwuenwN8QXn4OgCdYjiwFF8qNQRXlwap] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FrXUmEDUMcOaMHuV2tsrOBex719Mr5DF] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][FrXUmEDUMcOaMHuV2tsrOBex719Mr5DF] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8jqYWZF7T0f8NGzUrbPLDsZ0nVhWw9r6] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][8jqYWZF7T0f8NGzUrbPLDsZ0nVhWw9r6] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][q49l4l440EvhtelSVX92uO7EgQ8aGHBZ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][q49l4l440EvhtelSVX92uO7EgQ8aGHBZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][pTUQRWE4cUEkiZGndwVU1ItFEOsZa9kQ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][pTUQRWE4cUEkiZGndwVU1ItFEOsZa9kQ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][jkRHzO3XojdkWpQUXXwxDfzzaHr544eo] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][jkRHzO3XojdkWpQUXXwxDfzzaHr544eo] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][L40Gx9ZOiry0DWnJMiJRohuMgRQaKjqM] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][L40Gx9ZOiry0DWnJMiJRohuMgRQaKjqM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CSwBIECytCQXI3uTFo06gz3rfD22P1kp] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][CSwBIECytCQXI3uTFo06gz3rfD22P1kp] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2Kol5XMqm4QwUFtiKz0EO6cZNsnQDWTO] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][2Kol5XMqm4QwUFtiKz0EO6cZNsnQDWTO] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4Ty3PnpI6mUtfZiuhGGWtOgtVm12RjkD] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][4Ty3PnpI6mUtfZiuhGGWtOgtVm12RjkD] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][uAa3L58PyjO28eoInFzbm7P9Ben9UQCb] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][uAa3L58PyjO28eoInFzbm7P9Ben9UQCb] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][pov4aopmUxtqqPGUq3sipkLMUnehbMgA] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][pov4aopmUxtqqPGUq3sipkLMUnehbMgA] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Hs7iKp6acIjRUOM8B8N0iqNaNSbNMXtY] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Hs7iKp6acIjRUOM8B8N0iqNaNSbNMXtY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DbAH9wENAwNOnADP4Rik1aUwY3DS5NU6] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][DbAH9wENAwNOnADP4Rik1aUwY3DS5NU6] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fkOWWnlO024x1c2xrR0bt8WfdOtGNKrm] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][fkOWWnlO024x1c2xrR0bt8WfdOtGNKrm] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Dc4I0RoySHzlTYGzcdzFuNjhgcqptaq6] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Dc4I0RoySHzlTYGzcdzFuNjhgcqptaq6] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][9DQAaM9VKLfsBwnbAjTpJQRXS8qTwmfx] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][9DQAaM9VKLfsBwnbAjTpJQRXS8qTwmfx] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][W4CANJCpg7foEng37b1kkecQSjTPx2kH] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][W4CANJCpg7foEng37b1kkecQSjTPx2kH] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][32AThYWBhEh4Ar7Nk6xwyMKT35yBjC8B] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][p4bbsEoCIhv7kc1GkU0RhJbaxw7ePLFG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sBRA7wBXDPWdMEl3qfReG79YGLU27QZN] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][sBRA7wBXDPWdMEl3qfReG79YGLU27QZN] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3acIZtKjnqp3kW7SPpJ9YALl4JR87RUf] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][3acIZtKjnqp3kW7SPpJ9YALl4JR87RUf] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][BrHEtE0OwK6xZidc0coRebgznuklXGxC] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][BrHEtE0OwK6xZidc0coRebgznuklXGxC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lDc89q1EMIsVEzm3AzQezRZfN4uxzhqc] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][lDc89q1EMIsVEzm3AzQezRZfN4uxzhqc] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][VgEvGeC5lwIM2QdrKmSV8KHJoaXwv7KN] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][VgEvGeC5lwIM2QdrKmSV8KHJoaXwv7KN] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][HYA6IjbO2dhAfSD69UU3eTNAWYsmKPyk] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][HYA6IjbO2dhAfSD69UU3eTNAWYsmKPyk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][gRwm0UaTYDhwLhs6YveGoaHJAZ00G1AH] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][gRwm0UaTYDhwLhs6YveGoaHJAZ00G1AH] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wVLL3KBgXOMFLpjr3IauSFPIhf9epBON] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][wVLL3KBgXOMFLpjr3IauSFPIhf9epBON] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][SYPR6TOBigos2V2OtwMEmpFoSj9BfhOb] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][0Xacj1ekhOHe9fwhVrDsyh6Z4F1ykuUx] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][0Xacj1ekhOHe9fwhVrDsyh6Z4F1ykuUx] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OqUyPmSqjDsdWmHcERLRXHdAIqiwoQtj] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][OqUyPmSqjDsdWmHcERLRXHdAIqiwoQtj] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2ymlAELsldiw4inoNKIrgv0S8GovIokC] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][2ymlAELsldiw4inoNKIrgv0S8GovIokC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IgLNhCC68bNe9bcE251SB0s8sA329ppu] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][IgLNhCC68bNe9bcE251SB0s8sA329ppu] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5gQmsH8NwvfPdBRrZTqVHq9rLjdj4UDW] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][5gQmsH8NwvfPdBRrZTqVHq9rLjdj4UDW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][pF9fZSswXZgBywRjN4VUts1P6sriI2CV] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][pF9fZSswXZgBywRjN4VUts1P6sriI2CV] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][K7xD29pZFhyE6uPfo2KZfIkO9pK4GNpq] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][K7xD29pZFhyE6uPfo2KZfIkO9pK4GNpq] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][A4XWA5bEh98IciMbJBlzxATlcRIxyHnr] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][A4XWA5bEh98IciMbJBlzxATlcRIxyHnr] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yU9UMkLa4iE0trFngBM0u5cpNVKi3lbQ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][MqnB6Pb0yGzDT3PiWiqEz7Hs8b4TIGim] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][SYPR6TOBigos2V2OtwMEmpFoSj9BfhOb] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][yU9UMkLa4iE0trFngBM0u5cpNVKi3lbQ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wNfS5rlzZlqdxD2vUHD8K9aAktcp4R9Z] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][32AThYWBhEh4Ar7Nk6xwyMKT35yBjC8B] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MqnB6Pb0yGzDT3PiWiqEz7Hs8b4TIGim] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MA8jjv4WS1pogMvKannZgdBuyF33wk9v] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][k962MqzElytCymRuzPyoKxMvT6eL3gYg] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][F6d7YLVhkVBF98QTN9JMCvkAI8EKSdbb] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wNfS5rlzZlqdxD2vUHD8K9aAktcp4R9Z] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][MA8jjv4WS1pogMvKannZgdBuyF33wk9v] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][nIiQCWq3e16SlNQ5cb34xbsdF1PekqDr] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][k962MqzElytCymRuzPyoKxMvT6eL3gYg] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][H2Sq792QETxqfOYKHt1FIDnVY0eVicST] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][F6d7YLVhkVBF98QTN9JMCvkAI8EKSdbb] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mYnMal0NOX178gE7STGNkzrWVgFSEWDx] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][nIiQCWq3e16SlNQ5cb34xbsdF1PekqDr] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MOFIzbVsPWZUoHMxITkEL1HpN9rrA8lM] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][H2Sq792QETxqfOYKHt1FIDnVY0eVicST] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][eLtqf2aoPpNAKdbAX0JzLMb979ylRO49] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][mYnMal0NOX178gE7STGNkzrWVgFSEWDx] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][KDZpdngRKv9ZF8EAh1vLl2BQUMVuRqMN] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MOFIzbVsPWZUoHMxITkEL1HpN9rrA8lM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eLtqf2aoPpNAKdbAX0JzLMb979ylRO49] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][A945V60DUdmdusY1Xbk58DyAgRpA23mX] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][7fD2KeQofaQgRrcZgDSFn3ltaD7bbq35] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][TrJuQ45bsWId2dDCqWmbYLnVRH1ybgcd] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KDZpdngRKv9ZF8EAh1vLl2BQUMVuRqMN] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][A945V60DUdmdusY1Xbk58DyAgRpA23mX] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][wfr7dl9d7xbSRLivVBgS7GMUPlzlnuI7] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][7fD2KeQofaQgRrcZgDSFn3ltaD7bbq35] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KOGrSuB4PaJ4KfsLAcxDx5na1mcl1avH] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TrJuQ45bsWId2dDCqWmbYLnVRH1ybgcd] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][j3eqIhZhlvd2Y8lBXutmu9XqQmnF4tYG] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][cOSGWaWjQbOKHYkaRch8comYRrCsntfc] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wfr7dl9d7xbSRLivVBgS7GMUPlzlnuI7] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][KOGrSuB4PaJ4KfsLAcxDx5na1mcl1avH] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][vhXPId8SfaguaxeLBSUNtfu0dOYstZwE] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][j3eqIhZhlvd2Y8lBXutmu9XqQmnF4tYG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2upaXcMg1Vymt5YqOCz2XhgO7CNARlO7] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cOSGWaWjQbOKHYkaRch8comYRrCsntfc] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][melggAS7wCI2kU8Y6Xs4gC2Lx5CcSmI2] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][08jzYBXs9TIrTR8ePoPuKme8hRMMSX1R] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vhXPId8SfaguaxeLBSUNtfu0dOYstZwE] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][2upaXcMg1Vymt5YqOCz2XhgO7CNARlO7] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][aOdPVwcA0Lksw42Y2NLEyEwSl7pqMSO4] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][melggAS7wCI2kU8Y6Xs4gC2Lx5CcSmI2] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][X6T3RS0FUNkkQmpSGxxRqvlC0OCNwle8] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][08jzYBXs9TIrTR8ePoPuKme8hRMMSX1R] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][RRzwFTLO4xL2YuxO2GDsXSjbUIlFUgqB] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hqavth1E86ExA6ZOfUmkOl6lMiBD1jkl] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][aOdPVwcA0Lksw42Y2NLEyEwSl7pqMSO4] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][X6T3RS0FUNkkQmpSGxxRqvlC0OCNwle8] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][9J9u5KCXaBZAKr9SCYWslUFm2XZuTRGT] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][RRzwFTLO4xL2YuxO2GDsXSjbUIlFUgqB] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ETJSrkZaB4wgaNZpCvmZHGq8bW7MQxtK] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hqavth1E86ExA6ZOfUmkOl6lMiBD1jkl] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][uQuZZacGi3javUoWD3jhnQf8lbhBTc86] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][9J9u5KCXaBZAKr9SCYWslUFm2XZuTRGT] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sqMYdr9M8qdvp1iKk20DV1vhNmDfOcYJ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ETJSrkZaB4wgaNZpCvmZHGq8bW7MQxtK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][AVs7tYaDVQ14Cd3FeKE6tQWOTonVDeqU] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][uQuZZacGi3javUoWD3jhnQf8lbhBTc86] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PTnVdwxeFGVZgt1yAMkle4IxjZib4hcl] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sqMYdr9M8qdvp1iKk20DV1vhNmDfOcYJ] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][imk5WLRkHDOq9Q8I8ESBCwImJuUMkAZO] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][AVs7tYaDVQ14Cd3FeKE6tQWOTonVDeqU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][baxtk8pSVtX6UtIiceKvSaaa8uVXVz8f] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PTnVdwxeFGVZgt1yAMkle4IxjZib4hcl] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][KTelyHapGsMRYgBWCDdl5Gy4nzQCnSAk] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][imk5WLRkHDOq9Q8I8ESBCwImJuUMkAZO] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ZCpwpkG4aBr6KdLqgjL1LroFQMUXdtIY] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][baxtk8pSVtX6UtIiceKvSaaa8uVXVz8f] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ycLXv4LkgyqpMxgo7Fs6IiNrhOnDsyfB] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][EDiGwT6XUMol4AgovDg1txbRE1n4HCpe] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][KTelyHapGsMRYgBWCDdl5Gy4nzQCnSAk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ZCpwpkG4aBr6KdLqgjL1LroFQMUXdtIY] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ycLXv4LkgyqpMxgo7Fs6IiNrhOnDsyfB] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][iyTGfrIT7V6ARHcdRD9zssNccddR6zd5] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][iqZ9lxodaxF5VF8thzyUjwJqryX0s74V] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][EDiGwT6XUMol4AgovDg1txbRE1n4HCpe] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][faOxU8x3I8Qb3ievwIfcfidk7EY69MMk] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][NUsXDewU6jiou66KCsWjfpaMdvg971U0] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][iqZ9lxodaxF5VF8thzyUjwJqryX0s74V] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][iyTGfrIT7V6ARHcdRD9zssNccddR6zd5] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][faOxU8x3I8Qb3ievwIfcfidk7EY69MMk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][gnUrfR6tmpawTyn1FVpCZm4Y0eFiWJiS] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IZDgGx1oJv7oE7SFLMb4zxb3vHxCrnFg] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tUp55k6882WpV53jPmfYl05dcWmeXqAe] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][NUsXDewU6jiou66KCsWjfpaMdvg971U0] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][g1sjKL8wjPHdSuswUsFpmACP0M3mGkyx] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IZDgGx1oJv7oE7SFLMb4zxb3vHxCrnFg] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][gnUrfR6tmpawTyn1FVpCZm4Y0eFiWJiS] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tUp55k6882WpV53jPmfYl05dcWmeXqAe] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][wZyWPQSlDBStuNIRV09Rp6Vlz9RdVSkc] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qVJYniRlvN7v3zrNcA7QbbpK4q6CCAgV] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][g1sjKL8wjPHdSuswUsFpmACP0M3mGkyx] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][134hCf2KH1eD6KjPo9bNAzQqrL7wUnUH] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][3xsdBKkJHMTctxJptA8mB1slBPPO0Gh6] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][qVJYniRlvN7v3zrNcA7QbbpK4q6CCAgV] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wZyWPQSlDBStuNIRV09Rp6Vlz9RdVSkc] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][134hCf2KH1eD6KjPo9bNAzQqrL7wUnUH] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3xsdBKkJHMTctxJptA8mB1slBPPO0Gh6] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][U5etjv6SRNMnGfhEqtJfVwte2My4YFbs] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WOxUB8FgmUOoztIsJeBAYfymckNkkGHv] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KItklXkW7F3uDvJfhMqzIPG4XrDyYfLk] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][f7vBh8o644pUBYWVkP7GiHvpereCkfos] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][WOxUB8FgmUOoztIsJeBAYfymckNkkGHv] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][U5etjv6SRNMnGfhEqtJfVwte2My4YFbs] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KItklXkW7F3uDvJfhMqzIPG4XrDyYfLk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KTUW7i9CTb85pRVTw4vLPEe9XPM4kbRF] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][f7vBh8o644pUBYWVkP7GiHvpereCkfos] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wQsYPvxYQXErTMX6YR6LUMxPl9eXX68H] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][36HKpLXAjSxBxS6NpDlLSG5yq7oqHhjj] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][HfF54aifBjAz6KiEUQy7JMkPR9TLgSQ4] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KTUW7i9CTb85pRVTw4vLPEe9XPM4kbRF] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wQsYPvxYQXErTMX6YR6LUMxPl9eXX68H] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][RTkrGDa4wPWeYRqXoOLcw1VQOxRcirBq] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][08Gm1WT8AXgmoHmftR8bu2wkbOCaumLK] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][36HKpLXAjSxBxS6NpDlLSG5yq7oqHhjj] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][HfF54aifBjAz6KiEUQy7JMkPR9TLgSQ4] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][U9BQ4XHFkW9uarABai1GLRvGcYz4lFG8] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][wqOHpNBtWtcDKDBUFixixcmXxOMJC9iM] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][RTkrGDa4wPWeYRqXoOLcw1VQOxRcirBq] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][08Gm1WT8AXgmoHmftR8bu2wkbOCaumLK] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Ba73FZW4dzvPFGvQGZA26wXQcpIYsxM2] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][U9BQ4XHFkW9uarABai1GLRvGcYz4lFG8] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][SPVjE9PbCLQWz9ASUaljOLI2qZjlw1qF] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wqOHpNBtWtcDKDBUFixixcmXxOMJC9iM] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][trrk8nrtulGns9ch3x2swMcSFQUzPKjY] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Ba73FZW4dzvPFGvQGZA26wXQcpIYsxM2] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0YlHhVTQczwIFmqHMlcZZoge6363Ijco] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][SPVjE9PbCLQWz9ASUaljOLI2qZjlw1qF] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][trrk8nrtulGns9ch3x2swMcSFQUzPKjY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eHmyLptbVJCM8JeEcKCMaN2learHIohl] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OFG1O2X3Mljn5x8QX2GeyXhpVhunQhhB] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0YlHhVTQczwIFmqHMlcZZoge6363Ijco] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][eZIxU0wwKkIdq5zunjHucWb7whusICAe] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eHmyLptbVJCM8JeEcKCMaN2learHIohl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OFG1O2X3Mljn5x8QX2GeyXhpVhunQhhB] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bsHtNwUSLO7v3xGp202RaaRfRP7dwai2] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][MxhWf9fKuiOLEpsUY31kzcwVDr9UtwNy] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Gnii5tVvbHi1SwnuLIyCnBJeSID69xk1] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eZIxU0wwKkIdq5zunjHucWb7whusICAe] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][bsHtNwUSLO7v3xGp202RaaRfRP7dwai2] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][xJiiIoXuh3Y7U4OqjuJ4QMLbTF3YQK61] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MxhWf9fKuiOLEpsUY31kzcwVDr9UtwNy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Gnii5tVvbHi1SwnuLIyCnBJeSID69xk1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][A1PacYZzCtLhrGzKxX40O35BR1zAeUiB] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][VCioVM3PibiPSUJWmu4WmzcNsz4Fbp9K] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][7rCdnrqZFAHOpQOBbhjqEhwKiQGrOAZ8] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xJiiIoXuh3Y7U4OqjuJ4QMLbTF3YQK61] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][A1PacYZzCtLhrGzKxX40O35BR1zAeUiB] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fvNLDleg0zTWzzw8NRNF5G86eFWk2TSd] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][VCioVM3PibiPSUJWmu4WmzcNsz4Fbp9K] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][7rCdnrqZFAHOpQOBbhjqEhwKiQGrOAZ8] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][HmRsIpWBRU9gPua5q6UvfUfd5sIaBjdL] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YF1daNehDCp752NHbQWhYhfh0SZkbiNV] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fvNLDleg0zTWzzw8NRNF5G86eFWk2TSd] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][aJszMLlg9V2UROTaSM4g8c32x4xxADwO] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][AmnBxDfYIXIP9AdNafpEdphHDzvMaheY] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][HmRsIpWBRU9gPua5q6UvfUfd5sIaBjdL] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][YF1daNehDCp752NHbQWhYhfh0SZkbiNV] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][aJszMLlg9V2UROTaSM4g8c32x4xxADwO] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][d5go9ef7A1WMlwpl8pMCqrMu1zejmIXW] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ZDp8YtEkL5s9vLzZk9k0HBuMOPlc8oRl] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][hcPJEjRZlRGQ6HRV1N0v98sooPJ7AqIL] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][AmnBxDfYIXIP9AdNafpEdphHDzvMaheY] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][d5go9ef7A1WMlwpl8pMCqrMu1zejmIXW] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][ZDp8YtEkL5s9vLzZk9k0HBuMOPlc8oRl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][UoXUkFepNAYKpMSYwKffZ9XBSs0Zdb1i] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hcPJEjRZlRGQ6HRV1N0v98sooPJ7AqIL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Xh3ipq6OCSNvuKkDIYTARhDniB0EKuj4] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][22uTEHinSF5fyp7bKxTPY83sHxPGE5KZ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][pqCnBh3RsyaGr93grVCQk0I7i4rFEAW0] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][UoXUkFepNAYKpMSYwKffZ9XBSs0Zdb1i] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Xh3ipq6OCSNvuKkDIYTARhDniB0EKuj4] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][22uTEHinSF5fyp7bKxTPY83sHxPGE5KZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8kJxufePdtQkzpReUCwWaIb6iILD4nvb] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][pqCnBh3RsyaGr93grVCQk0I7i4rFEAW0] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zQF0eBFkg6AjptSNA3FTTIhoisqRkmeH] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][z6BZXsb4AhmFNXMLPpKRh3LDoysFS6DY] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][zd2bc9mw5I7CExPtbf6rlhsS77Xh6rFP] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][8kJxufePdtQkzpReUCwWaIb6iILD4nvb] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][z6BZXsb4AhmFNXMLPpKRh3LDoysFS6DY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][nI7n4eVI0zhfKiPuplmC2feczYf2xIgx] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zQF0eBFkg6AjptSNA3FTTIhoisqRkmeH] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zd2bc9mw5I7CExPtbf6rlhsS77Xh6rFP] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][J57xECFpUkz04p9YzNMo9BjFjCp5CFzM] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][OhvPXVQK0zpIDZsuV8hCfM5IiFJ6fglR] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FO15K0t50XRwbOUmdoSGZTykDoHSi0sC] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][nI7n4eVI0zhfKiPuplmC2feczYf2xIgx] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][XZx9mN5tNsZdIJgpScqHjBchfSqyZDlr] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OhvPXVQK0zpIDZsuV8hCfM5IiFJ6fglR] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FO15K0t50XRwbOUmdoSGZTykDoHSi0sC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][J57xECFpUkz04p9YzNMo9BjFjCp5CFzM] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Ar09or07wX1DPAgDTVopNt4Q6PDyeCst] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1IVlgd6aD7hdh9K39O1uwlrfDiI6oviN] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WAdnE71ObH1LxyP7nfuexuoYpy3ySF0v] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][XZx9mN5tNsZdIJgpScqHjBchfSqyZDlr] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][It2OXbMXW0f3PpfzyjbL9JqD2h3SCiSp] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Ar09or07wX1DPAgDTVopNt4Q6PDyeCst] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1IVlgd6aD7hdh9K39O1uwlrfDiI6oviN] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WAdnE71ObH1LxyP7nfuexuoYpy3ySF0v] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][YPlusWuJopoE08q0emrB7RKaSO1jQAQH] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][m61UXDVKoSNZ4Zjk5MkPEsuGNhxvltWJ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][It2OXbMXW0f3PpfzyjbL9JqD2h3SCiSp] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bxUnbMN88IIPusRQiS9Xbp8DK6CsqgpZ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][lgmQrrLwggaqaVKxh581TooJxvrRMITp] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][m61UXDVKoSNZ4Zjk5MkPEsuGNhxvltWJ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YPlusWuJopoE08q0emrB7RKaSO1jQAQH] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][bxUnbMN88IIPusRQiS9Xbp8DK6CsqgpZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][kzYRATLdCBc42JypDS7r5aLn85bHcWhC] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][m5fKZsPn17AVV6zFSR1FmZO27B5c0Odj] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lgmQrrLwggaqaVKxh581TooJxvrRMITp] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8fXKjV3TCC33FFR8DvL2iFUKzXe2Q8PU] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][uXzrrIV9Q3Rdjd0it5avKDhpFXyrSeS0] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][kzYRATLdCBc42JypDS7r5aLn85bHcWhC] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][m5fKZsPn17AVV6zFSR1FmZO27B5c0Odj] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][C3F7g7GwF0jlJdpWoFQpsqP0hrZgiswV] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8eF1AZIOdqLgqnCVeBA7wt2XqCzXA4hI] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8fXKjV3TCC33FFR8DvL2iFUKzXe2Q8PU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][uXzrrIV9Q3Rdjd0it5avKDhpFXyrSeS0] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][BpDcnXebH5TQixosdyK4YAXypyvNOtN4] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qcFRT30XEUlR5PILvt8sNI0WqQvZcU5X] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][C3F7g7GwF0jlJdpWoFQpsqP0hrZgiswV] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8eF1AZIOdqLgqnCVeBA7wt2XqCzXA4hI] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][YH7T8S1bv0wpxMcQAbhnZPjlK1fvxTra] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][W6jWvwHnalXklCvvLOr9ojPsj1mfrc0F] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qcFRT30XEUlR5PILvt8sNI0WqQvZcU5X] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][BpDcnXebH5TQixosdyK4YAXypyvNOtN4] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][w9rrBmRHx3z2cTAabvtO0tCplzfAPkJm] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lQZob9ghcOEXRxuxSwfazueP2GmRFyiX] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YH7T8S1bv0wpxMcQAbhnZPjlK1fvxTra] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][W6jWvwHnalXklCvvLOr9ojPsj1mfrc0F] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][vq0Z2wwnrKzEhU0vwq8bWVf4Wg9MUpXF] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][w9rrBmRHx3z2cTAabvtO0tCplzfAPkJm] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lQZob9ghcOEXRxuxSwfazueP2GmRFyiX] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xX0yc2LZxOtnX1krFqUqPGrswp3xiIQb] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][RYwtnihyzBYsW4wuH4Y8tAkgfKIxozWx] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][lMmGczTvxADjWPQxSftp5kyLl7c7Bfuz] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vq0Z2wwnrKzEhU0vwq8bWVf4Wg9MUpXF] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][xX0yc2LZxOtnX1krFqUqPGrswp3xiIQb] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][NsuKma6C0GGaEg80SAsUJWOvYgQiKfIs] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YIqmkMNF5ao3WZr3NL4AVxPNILvMDcKg] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][RYwtnihyzBYsW4wuH4Y8tAkgfKIxozWx] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lMmGczTvxADjWPQxSftp5kyLl7c7Bfuz] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][nLL0iSvbYy37FPIsU6h2C2xxSwWrrSCo] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PC9jLEdmgU4gI0iqH7SfnjZyv2FSAh1R] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YIqmkMNF5ao3WZr3NL4AVxPNILvMDcKg] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][NsuKma6C0GGaEg80SAsUJWOvYgQiKfIs] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zxdHMvSOJBvZLxnsSOPAE4n2eeJbbrpj] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][zxdHMvSOJBvZLxnsSOPAE4n2eeJbbrpj] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8PjMye9aPQkPKiIKXCwLvcQOpsIq5xqQ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][8PjMye9aPQkPKiIKXCwLvcQOpsIq5xqQ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Xev7sZEVGxcW1a1Ht3YmgNvfJtuID2PW] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Xev7sZEVGxcW1a1Ht3YmgNvfJtuID2PW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lUecsU8PvheKe0tBiQPq0S45gVSDvoRu] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][lUecsU8PvheKe0tBiQPq0S45gVSDvoRu] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GTWLKWOk4GbxPvZZK2WpfpRkEnkyk6Cb] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][GTWLKWOk4GbxPvZZK2WpfpRkEnkyk6Cb] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wpx8pbLv2LFsQpvoysSkF70TuqFLtMD0] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][wpx8pbLv2LFsQpvoysSkF70TuqFLtMD0] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PJI3lpL5mSqw2tWghluMph7QQNzJnbzS] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][PJI3lpL5mSqw2tWghluMph7QQNzJnbzS] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2T0XkoNdTUfxmzVqcXh2JT1bB3XoszBj] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][2T0XkoNdTUfxmzVqcXh2JT1bB3XoszBj] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MAvZuSuKztgYZhigmIr04sdjr20Rr7Ot] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][MAvZuSuKztgYZhigmIr04sdjr20Rr7Ot] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][gmMnhU9FrEPbfYhDw54jQ8xObJsN7Qs7] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][nLL0iSvbYy37FPIsU6h2C2xxSwWrrSCo] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xfgvDRynaTPiVFPupwqcdpWaFFo61Lqa] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][xfgvDRynaTPiVFPupwqcdpWaFFo61Lqa] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][nZFtXSEziJIAzjQYVKESzCesCpsKUDNG] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][nZFtXSEziJIAzjQYVKESzCesCpsKUDNG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2Zz7lWttcD58JCGJWdo6hfRzfhUMVlV0] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][2Zz7lWttcD58JCGJWdo6hfRzfhUMVlV0] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JltljXqirmzdyX6KQ9Yr0WtUQpoZzA7k] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][JltljXqirmzdyX6KQ9Yr0WtUQpoZzA7k] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][pplAtlJF1yuRTpc2ULixy76PrzuetNCr] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][pplAtlJF1yuRTpc2ULixy76PrzuetNCr] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sP8FFs8Z4m9lmrZGACGRzdIyF9UTsTB9] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][sP8FFs8Z4m9lmrZGACGRzdIyF9UTsTB9] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GW2cFYzOXSqpZEacDvWcIGFQbfD4PKYS] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][GW2cFYzOXSqpZEacDvWcIGFQbfD4PKYS] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][okUK0Jl2zuAmfuT1ZUkExF0pzuZWLSV5] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][okUK0Jl2zuAmfuT1ZUkExF0pzuZWLSV5] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][VztURVigLTV6A2twPvRyD2wUALjOBhDQ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][VztURVigLTV6A2twPvRyD2wUALjOBhDQ] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][PC9jLEdmgU4gI0iqH7SfnjZyv2FSAh1R] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bXuJrcN9yyzB7d0TF93RuQG7MKgOhz2M] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][bXuJrcN9yyzB7d0TF93RuQG7MKgOhz2M] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][iNK6wXRtr197GXTdopDmftUSgk5oTmsK] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][iNK6wXRtr197GXTdopDmftUSgk5oTmsK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][i1LoX98SsxYAHzwjnPGBKT623B3HU6gx] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][i1LoX98SsxYAHzwjnPGBKT623B3HU6gx] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xLLhmcGYjcgaM0PMwyCnMvKFRSEdb0MW] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][xLLhmcGYjcgaM0PMwyCnMvKFRSEdb0MW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][De5WTkOzzrpHRUSFCsVfhxB73AYj5TTL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][De5WTkOzzrpHRUSFCsVfhxB73AYj5TTL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hgm4YSMtFrNtFgbumaoI6AIpG2T2Bs98] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][hgm4YSMtFrNtFgbumaoI6AIpG2T2Bs98] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PQNa3xCdIF6AN6F1LVyrmRMtlnKWDJHv] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][PQNa3xCdIF6AN6F1LVyrmRMtlnKWDJHv] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OW7DLMDTUWauKm7nmwSoMBNFzLGdfgXy] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][OW7DLMDTUWauKm7nmwSoMBNFzLGdfgXy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][XggfDBbydNOWcm7RZD810sDxij8pPSzw] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][XggfDBbydNOWcm7RZD810sDxij8pPSzw] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][XLb0UNmfn1sa5azf2ySSmYB51NM6z5rf] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CZGedQ8kfHygojl5eJ3Vz5coOMP3MaJc] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][CZGedQ8kfHygojl5eJ3Vz5coOMP3MaJc] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GA1HZjOXtL7yox1D1gRf9G8tg5HXrNPL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][GA1HZjOXtL7yox1D1gRf9G8tg5HXrNPL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yWQiUUgpT6FoqFd1OgZHjXgP1wssDS3f] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][yWQiUUgpT6FoqFd1OgZHjXgP1wssDS3f] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fRNLxwFYPT10VTimYJ1WOkXJhEA8plFl] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][fRNLxwFYPT10VTimYJ1WOkXJhEA8plFl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][jW2GWH8BafvYpM6dE9IgNmSp6zapCuy7] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][jW2GWH8BafvYpM6dE9IgNmSp6zapCuy7] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sUF5LucoykMfPlxE12HW1NyiJbWexR7f] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][sUF5LucoykMfPlxE12HW1NyiJbWexR7f] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3nhfEhCX44qBifLN5q9yokXoKHiIJdPY] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][3nhfEhCX44qBifLN5q9yokXoKHiIJdPY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][aYNJzrVVRSrJB4Z91mSoGMEdsWjaZUR1] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][aYNJzrVVRSrJB4Z91mSoGMEdsWjaZUR1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Y8BtMJyDN90cPSA9Azhf8Zrcv5S6vOYK] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][Y8BtMJyDN90cPSA9Azhf8Zrcv5S6vOYK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][byWNGtfOSyvea8cqckoxTP8qKaebwWq7] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][gmMnhU9FrEPbfYhDw54jQ8xObJsN7Qs7] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][byWNGtfOSyvea8cqckoxTP8qKaebwWq7] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KTndbLG23w9kpJwTuP5RMbNS3ufOSHkO] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eDRWr9eFNSKECrjtYZqn4PEprKVqvBuv] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][XLb0UNmfn1sa5azf2ySSmYB51NM6z5rf] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JzZjupFHBj69PjgEd2vDOmY1E5MqAHrm] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][OJubajwKZXHi3XCLLFM254YRi6yhExC2] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KTndbLG23w9kpJwTuP5RMbNS3ufOSHkO] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eDRWr9eFNSKECrjtYZqn4PEprKVqvBuv] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JzZjupFHBj69PjgEd2vDOmY1E5MqAHrm] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][qBwgpdyp7XYSPRCqKRVqGpDhIHnAaLLK] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][96O7rHIJ1SoayXEeajJTMUU2rriMiVwN] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fX5eNeTJhuxB2umzBgmTBs3qBPTwSPnA] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OJubajwKZXHi3XCLLFM254YRi6yhExC2] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][xLX8aaukhW9cJmCzdSM1FDuXSrLp7xCq] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qBwgpdyp7XYSPRCqKRVqGpDhIHnAaLLK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][96O7rHIJ1SoayXEeajJTMUU2rriMiVwN] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fX5eNeTJhuxB2umzBgmTBs3qBPTwSPnA] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][REPGQADQQkIcamNIMztlhxcPub4HiyR5] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JfI7TX4eedvgrwxxIEscRPCZqRuXpZoi] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ktv9zGsRZhI2d1g5VLQ2pYv0F4hP45kk] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xLX8aaukhW9cJmCzdSM1FDuXSrLp7xCq] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][NhqXp0oVdYsdyubI08eZxQr3fwQqzl9O] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][REPGQADQQkIcamNIMztlhxcPub4HiyR5] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JfI7TX4eedvgrwxxIEscRPCZqRuXpZoi] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ktv9zGsRZhI2d1g5VLQ2pYv0F4hP45kk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][40Re6yW8SjJdoJXCNeCq9pi9LjXCZz2o] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][f9G1Bb2PjHhlbsv8Q8iW6x05UcNvIIPW] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Rx7KiqzWf6IkEEtXGMl1M619gbe7ugL5] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][f9G1Bb2PjHhlbsv8Q8iW6x05UcNvIIPW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vzkRFPpf4cTm5HrUlLyBdfDJhvutvLWS] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! read PDO after read action"
[2021-06-11 12:20:32][vzkRFPpf4cTm5HrUlLyBdfDJhvutvLWS] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][UWr9KAiiPS3Jj0xk74h4S0eRunk2F4lD] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][40Re6yW8SjJdoJXCNeCq9pi9LjXCZz2o] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][Rx7KiqzWf6IkEEtXGMl1M619gbe7ugL5] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][NhqXp0oVdYsdyubI08eZxQr3fwQqzl9O] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][HsUdaJT2gNa0rU7uHkme06O9IwhwSMDf] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Rmk3YE4lbBHPpCx7N09HOyzmSSzTffTD] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][BlMbE6TSs2Swa2A6frXBclFsGfHvDAtl] Processing: App\Jobs\WriteExpectWrite
"FAILURE!!! write PDO after read action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][HsUdaJT2gNa0rU7uHkme06O9IwhwSMDf] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][UWr9KAiiPS3Jj0xk74h4S0eRunk2F4lD] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][Rmk3YE4lbBHPpCx7N09HOyzmSSzTffTD] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][BlMbE6TSs2Swa2A6frXBclFsGfHvDAtl] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][rkkdm4oCDGXPci4QQ5MpYucjFxghZpLm] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5uPhGykYo7VYwNDwiyZ1TvN3t4OGTMzp] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][AXWWHOC3Lw49Q1ySiI0IYzlunRIksOee] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][0P0bZzQuJAifgZQaP8MUmQfCVQgeXCdt] Processing: App\Jobs\WriteExpectWrite
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][rkkdm4oCDGXPci4QQ5MpYucjFxghZpLm] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][5uPhGykYo7VYwNDwiyZ1TvN3t4OGTMzp] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][fJCqn3YUTEZopMhAp4oCmoiDqARJwc9j] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][0P0bZzQuJAifgZQaP8MUmQfCVQgeXCdt] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][AXWWHOC3Lw49Q1ySiI0IYzlunRIksOee] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][w2nJZxORQF7iuKaAscpg0aUrelsfBOCG] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1QI4trlN3cubnIeGjq5izyT2XyIVHdOG] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4vTdBC1fA4rcXEkubQqzMfujNKIHR56e] Processing: App\Jobs\WriteExpectWrite
"FAILURE!!! write PDO after read action"
"SUCCESS!!! Write PDO after write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][w2nJZxORQF7iuKaAscpg0aUrelsfBOCG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fJCqn3YUTEZopMhAp4oCmoiDqARJwc9j] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][1QI4trlN3cubnIeGjq5izyT2XyIVHdOG] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][XiJCXn5t0tnBCT3jYt55vEYyBZomKepR] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][V4FWUk8Y4df1ECHwa6P8o6X6Y44ZiXzk] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4vTdBC1fA4rcXEkubQqzMfujNKIHR56e] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][gyhtj78thr9zKP68BRjgtgJ5WVazmMte] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][c1r97vuaxDyGwLi86WsmaQvUkmsuu7La] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][V4FWUk8Y4df1ECHwa6P8o6X6Y44ZiXzk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][XiJCXn5t0tnBCT3jYt55vEYyBZomKepR] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][gyhtj78thr9zKP68BRjgtgJ5WVazmMte] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][P3HGLJOyBgZUOXbdtPMDuWa0bLl9WoqC] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][c1r97vuaxDyGwLi86WsmaQvUkmsuu7La] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][VR78FQOiFJhU70IdXUHrdGZYwbHNdDO3] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][Gl9JI6oJqMhlLpCiPSbZ25aTELS68lw6] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][Y3q0EJzqMqzMPLOc2lBVqxgLMhPx3olc] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][P3HGLJOyBgZUOXbdtPMDuWa0bLl9WoqC] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"SUCCESS!!! Write PDO after write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][Gl9JI6oJqMhlLpCiPSbZ25aTELS68lw6] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4UdG3Ik9D4aRa2RcCMe4tbfiLsYHwP99] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][VR78FQOiFJhU70IdXUHrdGZYwbHNdDO3] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][Y3q0EJzqMqzMPLOc2lBVqxgLMhPx3olc] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Pcw63ytsK16CXBWM3Cs5v8npeJ1j3qqF] Processing: App\Jobs\WriteExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][42gbRdrmlegsnDa4sGJ1P1MWqaTTJcmI] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][YOOrRoGGj2GzcWrsCWBlAce9V8mlSXyc] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4UdG3Ik9D4aRa2RcCMe4tbfiLsYHwP99] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][oK9BmojAhdBPowNC6x9ghcLbyD6KP7B4] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][oK9BmojAhdBPowNC6x9ghcLbyD6KP7B4] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YOOrRoGGj2GzcWrsCWBlAce9V8mlSXyc] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Pcw63ytsK16CXBWM3Cs5v8npeJ1j3qqF] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][42gbRdrmlegsnDa4sGJ1P1MWqaTTJcmI] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][J8PkpHDTq1HOMHBSxuyztnz9RaykPUub] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][Fq11aNQrhJCGSocUlGbLcfzcEAuh3FVE] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][w4rTmOIeWRNABs6tKx8SHPgJRGBI0wQP] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][UIZtdzdhe2YDE6lt1DQ4euqUmTKpf1xW] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][Fq11aNQrhJCGSocUlGbLcfzcEAuh3FVE] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][w4rTmOIeWRNABs6tKx8SHPgJRGBI0wQP] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][UIZtdzdhe2YDE6lt1DQ4euqUmTKpf1xW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][J8PkpHDTq1HOMHBSxuyztnz9RaykPUub] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][etQq0ked9HP2RbfM80fjC4sVw0JSPASr] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DmGta4Jq1eF8qyxKT7HNbUphFLx4042f] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CWkFogrJWLKs88z9aODfDdvD3GUH03a8] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][epoJ5rjQ3cfW0lqcYcCM3FLUI0o9Z651] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][etQq0ked9HP2RbfM80fjC4sVw0JSPASr] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DmGta4Jq1eF8qyxKT7HNbUphFLx4042f] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][epoJ5rjQ3cfW0lqcYcCM3FLUI0o9Z651] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yiIdiqh7dt76nCPL13k05bYSDPNAQyST] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CWkFogrJWLKs88z9aODfDdvD3GUH03a8] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][PKq2Akq7pDmJSMCmJXFexD9b5xpOzQsA] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][k9mznWQfBZKYTaWiHcT5OQNRSQCm6lwJ] Processing: App\Jobs\WriteExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][bvbLLpCXMVeEsi10Ba5oOV67I92rlVWL] Processing: App\Jobs\WriteExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][yiIdiqh7dt76nCPL13k05bYSDPNAQyST] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][PKq2Akq7pDmJSMCmJXFexD9b5xpOzQsA] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][QeTbm0GH9jvo3fhJRnjcFQ3u8uHg4StN] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][k9mznWQfBZKYTaWiHcT5OQNRSQCm6lwJ] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][bvbLLpCXMVeEsi10Ba5oOV67I92rlVWL] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][8F9WmAtjRxhWQfKEHlyVg8M2BNZwpP2P] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GQOF3mi77Cuyt3vujuHrb0uo3sLqrHt3] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tRtD7UoFNWRAOjTaFDGBU2bvSCVK1zvC] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"SUCCESS!!! Write PDO after write action"
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][8F9WmAtjRxhWQfKEHlyVg8M2BNZwpP2P] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][QeTbm0GH9jvo3fhJRnjcFQ3u8uHg4StN] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][GQOF3mi77Cuyt3vujuHrb0uo3sLqrHt3] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tRtD7UoFNWRAOjTaFDGBU2bvSCVK1zvC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][AV77oTnbfaGZYlLtcBM7yy2Ee9qXbsbA] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6FUmGQZB255k9wCyccvzlDJRxRZgjWKy] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][U3Or5uoyTmAZF8msTd6VhnJvZxykQktR] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][tFra5wRyciBjPsDae8k5HY8ikTf7lesU] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"SUCCESS!!! Write PDO after write action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][6FUmGQZB255k9wCyccvzlDJRxRZgjWKy] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][AV77oTnbfaGZYlLtcBM7yy2Ee9qXbsbA] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tFra5wRyciBjPsDae8k5HY8ikTf7lesU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][U3Or5uoyTmAZF8msTd6VhnJvZxykQktR] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][EVi8RUvBEzqekowFKuK4sCwj3BALTMNd] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][ofTgxckkCMCiG2lGcz5ef53gSqmuUm6p] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][4Lp659QDOlIntLzrYeDseCkalPWqnFfs] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Lo3j5XwS5qhiE0XjQ7Dx1XPd9xqgfzB9] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][Lo3j5XwS5qhiE0XjQ7Dx1XPd9xqgfzB9] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][EVi8RUvBEzqekowFKuK4sCwj3BALTMNd] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][4Lp659QDOlIntLzrYeDseCkalPWqnFfs] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ofTgxckkCMCiG2lGcz5ef53gSqmuUm6p] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][cfSPpm1Ih0KluryV1344nypNcfnhSPtv] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mKAYvwDzIWRJv9XEw7gV9Umg8tfNNDGI] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][B06rUJhvSEpk4IjhoevRuYHeXbEoMqSG] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][BxOh77qmhOXczgQlTFNFlGIzPNpCBgka] Processing: App\Jobs\WriteExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][cfSPpm1Ih0KluryV1344nypNcfnhSPtv] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][kv0gJdEH9m2Z2Mye2y3cqvGjnbBo75uH] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][B06rUJhvSEpk4IjhoevRuYHeXbEoMqSG] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][BxOh77qmhOXczgQlTFNFlGIzPNpCBgka] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][mKAYvwDzIWRJv9XEw7gV9Umg8tfNNDGI] Processed:  App\Jobs\WriteExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][RrewtVcOHCcm2fLiINjX1DixiI1N5TG6] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][hdj9D03PbqR3b3fdblWyF6CE5nayoQGF] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1BhnlsoZ5Ofha2PIsGXERoRKzClpqtt2] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][kv0gJdEH9m2Z2Mye2y3cqvGjnbBo75uH] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][FP6BGmiH6hBWSAD6z0ajvazR0ODZFT4z] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][1BhnlsoZ5Ofha2PIsGXERoRKzClpqtt2] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hdj9D03PbqR3b3fdblWyF6CE5nayoQGF] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][RrewtVcOHCcm2fLiINjX1DixiI1N5TG6] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][tGaptEj9UBkwDFhpgk3LmRrGrkMtjTIr] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][v6G9mPGMkxQRUu34acaghVWv63Dukxdw] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][J7SSXnDYcCZyOX3nvzYhwZ3IoN2G8YCr] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FP6BGmiH6hBWSAD6z0ajvazR0ODZFT4z] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][sA7WaeNW5VFqj5TONKT54v0AV4NXarbO] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][tGaptEj9UBkwDFhpgk3LmRrGrkMtjTIr] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][J7SSXnDYcCZyOX3nvzYhwZ3IoN2G8YCr] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][v6G9mPGMkxQRUu34acaghVWv63Dukxdw] Processed:  App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][iAK7MfMQRXHxQqDfy00JJ7venT9xbXQA] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][sA7WaeNW5VFqj5TONKT54v0AV4NXarbO] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][5xoWmlCfXBcZEzSHf8lLDX8lTWB4K52K] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][AC8jdky7tsUVxTkLu7GQ2b5AJNXko4YH] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][RwANDXHGQnKgAwWuIka1wwAkBT0CiMVT] Processing: App\Jobs\WriteExpectWrite
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][5xoWmlCfXBcZEzSHf8lLDX8lTWB4K52K] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][AC8jdky7tsUVxTkLu7GQ2b5AJNXko4YH] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][iAK7MfMQRXHxQqDfy00JJ7venT9xbXQA] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][3EYYWHPvv9zOYYVprspkd0vsGmld7NAS] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JsNRxHPxD0NP4iaCUB5TWzuoLNfJ9VZm] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][RwANDXHGQnKgAwWuIka1wwAkBT0CiMVT] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][0UyU5ZIAS67VelqF1N5IHMpZMWuadMEo] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][JaG8iL7wkyMvAou3XMUW6tSKrN6TedWB] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][3EYYWHPvv9zOYYVprspkd0vsGmld7NAS] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"SUCCESS!!! Write PDO after write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][uf1dHL4eCP4q4OE5GUm5fAyPROeCB5qN] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][JsNRxHPxD0NP4iaCUB5TWzuoLNfJ9VZm] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][0UyU5ZIAS67VelqF1N5IHMpZMWuadMEo] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][JaG8iL7wkyMvAou3XMUW6tSKrN6TedWB] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][k3CgmEyBIxa0dbFInkxMJotBXlMC3Uoz] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][uUvASwPM8MqhrJqhFiqYZiwddWiTpp17] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][x3uVPD5d714MkkhxnNijItTexCB0jUhY] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][uf1dHL4eCP4q4OE5GUm5fAyPROeCB5qN] Processed:  App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][1aidbCWH3yy5DU5h3NeKjlaKmFCuKVzF] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][uUvASwPM8MqhrJqhFiqYZiwddWiTpp17] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][k3CgmEyBIxa0dbFInkxMJotBXlMC3Uoz] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][x3uVPD5d714MkkhxnNijItTexCB0jUhY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][HF9koytljJwpgN6kwx77eMjTxN9JJbnX] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][pxyf6ub0sTJgZDIXfVy1WSFGUatRXo8u] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][XgKTtkMcmFTU0PNP8iqr1NgTndN1aX7U] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][1aidbCWH3yy5DU5h3NeKjlaKmFCuKVzF] Processed:  App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][pxyf6ub0sTJgZDIXfVy1WSFGUatRXo8u] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][HF9koytljJwpgN6kwx77eMjTxN9JJbnX] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][V7KiEzPTRFwjnrvUHBYUcjrcTQcWIYQa] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][XgKTtkMcmFTU0PNP8iqr1NgTndN1aX7U] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][PI3usYdgMVFRMC5HDOKH9yhfEJq9j6zE] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TMLXYDfjv8Ue429ko81LyXkX9AKGXZte] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][G6aIfUwdprVYyF84Ug24fzVLQaefpThT] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][V7KiEzPTRFwjnrvUHBYUcjrcTQcWIYQa] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][PI3usYdgMVFRMC5HDOKH9yhfEJq9j6zE] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TMLXYDfjv8Ue429ko81LyXkX9AKGXZte] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][TJeViNUJwgHXNvpWuZBtrq0408E28yYA] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][t8DemTi1OvaEHdypLNlwqYutvRfRT0h4] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yEYvTtFAzYDWZXteTupbBt1vpkxH2ZhK] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][G6aIfUwdprVYyF84Ug24fzVLQaefpThT] Processed:  App\Jobs\WriteExpectWrite
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][BDhPhnIWKngmaOTlbHypsPwAYHmkIsHw] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][t8DemTi1OvaEHdypLNlwqYutvRfRT0h4] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yEYvTtFAzYDWZXteTupbBt1vpkxH2ZhK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TJeViNUJwgHXNvpWuZBtrq0408E28yYA] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][DOIKtLJUWrowgAlvSCEcwmZVq6mm0mlx] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][Z5vOBgyebEjSwDHnw3SyVLJ9P8itkAMb] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][Z5vOBgyebEjSwDHnw3SyVLJ9P8itkAMb] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][PqBFLyNhQLWE9J5lhn1Eb3JNUnYZFOwi] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][PqBFLyNhQLWE9J5lhn1Eb3JNUnYZFOwi] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][APANAFFtNPeId8hQ9lxAvVUpyxJbVW96] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][APANAFFtNPeId8hQ9lxAvVUpyxJbVW96] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][aCdBcmBg8MjgF1iX7nCnl9zqdndRohcJ] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][aCdBcmBg8MjgF1iX7nCnl9zqdndRohcJ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][z7fznrAATev1SDPnCBj4hapXOhm4qchZ] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][z7fznrAATev1SDPnCBj4hapXOhm4qchZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ErvRFr5r5z1sFiqdCcNyaFxGksnYcBXU] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][ErvRFr5r5z1sFiqdCcNyaFxGksnYcBXU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][9TWEfbpKCf5cc7mitpAgaV2awB3dEj58] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][9TWEfbpKCf5cc7mitpAgaV2awB3dEj58] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6iTNquNOEmzEkc1GUbcqAQIBYK7BbDcZ] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][6iTNquNOEmzEkc1GUbcqAQIBYK7BbDcZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][51nITshCLoodO4xIycSQsOFp9kV39mCs] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][51nITshCLoodO4xIycSQsOFp9kV39mCs] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][S8vI2kRD56gJqo53SH8huCGiJeI6wQKf] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][BDhPhnIWKngmaOTlbHypsPwAYHmkIsHw] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][lXJ9sjuWMTNIn9UQJyqcMihzMhmC9Nxw] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][lXJ9sjuWMTNIn9UQJyqcMihzMhmC9Nxw] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][lAw3AoI2pAL79vF5Ym3oq3GBjbFMe8IZ] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][lAw3AoI2pAL79vF5Ym3oq3GBjbFMe8IZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][p4mrLmQgBKMgwPKgpj8mi7It4GJhFupp] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][p4mrLmQgBKMgwPKgpj8mi7It4GJhFupp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hhPzbK0YfOwd5cXjpe3Tm3NR7k1iuuhF] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][hhPzbK0YfOwd5cXjpe3Tm3NR7k1iuuhF] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][c0lEMsC6d9bW5QgVYchJacqw3kVYoEjM] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][c0lEMsC6d9bW5QgVYchJacqw3kVYoEjM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][nntqfDNnoUd8QLZOd2mjbwNe0furObuM] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][nntqfDNnoUd8QLZOd2mjbwNe0furObuM] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][79cIkna0RbDr9eF3d0PAPxx29h1Cxftr] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][79cIkna0RbDr9eF3d0PAPxx29h1Cxftr] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][WDzMX9AdjlldHKivgAZKxBPs3lhKOfgK] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][WDzMX9AdjlldHKivgAZKxBPs3lhKOfgK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][nKOrNmhK2dB6oOGXlGs92LQRJWwqURrs] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][nKOrNmhK2dB6oOGXlGs92LQRJWwqURrs] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ln4wj66U7JmdB6Fdvd5gw8cjSEU92UR8] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][n4OezYPYw61IlnbcSgRYGJriK98woaYj] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][n4OezYPYw61IlnbcSgRYGJriK98woaYj] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][E9krnD2Ww1eWRvTwdE8ktcpZOTDrsv4W] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][E9krnD2Ww1eWRvTwdE8ktcpZOTDrsv4W] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][QIOzVg3ovdpPVJQqrguI0v1nSGGkbr7I] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][QIOzVg3ovdpPVJQqrguI0v1nSGGkbr7I] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6VNS3IP857k8mImg9KWlqBi4x9a1vPEn] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][6VNS3IP857k8mImg9KWlqBi4x9a1vPEn] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][PdyWRja5yPU9qigxjxbzbMSjXPd3r2EU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][PdyWRja5yPU9qigxjxbzbMSjXPd3r2EU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][R4J6I0DMfo0C1C42BWKoldusUOLTMcMS] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][R4J6I0DMfo0C1C42BWKoldusUOLTMcMS] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][GXonxzTfaQFwGnvZiK5lUVJJBZTffZGo] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][GXonxzTfaQFwGnvZiK5lUVJJBZTffZGo] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][NgYdCrQb9G3RZy5siIEg8v5UozQ5oQSk] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][NgYdCrQb9G3RZy5siIEg8v5UozQ5oQSk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][0QKc4VUcSRYbLfbClhwWsr0xpw3hOGpE] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][0QKc4VUcSRYbLfbClhwWsr0xpw3hOGpE] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KQiX5b6ZKJwimLmfKCRLEYHRA5KIkBTz] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][KQiX5b6ZKJwimLmfKCRLEYHRA5KIkBTz] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][9A6HZhUb1pTpLXBhSrK4Yup0CN2z31HJ] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][DOIKtLJUWrowgAlvSCEcwmZVq6mm0mlx] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][YrP9VnvT5ZgUTWWiPvBFyhCUZU1uA50D] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][YrP9VnvT5ZgUTWWiPvBFyhCUZU1uA50D] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][lTFECePVVMaPY8rzi1nqpT0YijqRZfoR] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][lTFECePVVMaPY8rzi1nqpT0YijqRZfoR] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][T9G3Bua5a9BiRRiHjS5vgV3RjHjtDLah] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][T9G3Bua5a9BiRRiHjS5vgV3RjHjtDLah] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][bJEPHC4Ce0unwtBkiDHpKsDYFUJUkfdD] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][bJEPHC4Ce0unwtBkiDHpKsDYFUJUkfdD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lNXMbLuQ3EUFV6kzFzCWkVV1fzIdmrAd] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][lNXMbLuQ3EUFV6kzFzCWkVV1fzIdmrAd] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][N8J96fC6zXaGJFxE6ZA0zABsfsfCmIHt] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][N8J96fC6zXaGJFxE6ZA0zABsfsfCmIHt] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][o57teTPNv4tv0m5iGRd5fijU7HpM9Y8a] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][o57teTPNv4tv0m5iGRd5fijU7HpM9Y8a] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][K2lDF4QIHwmilWSWrxlNc8EkAHSWzXul] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][K2lDF4QIHwmilWSWrxlNc8EkAHSWzXul] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bslEQXHIfLPqB80gS7jW0lEbD6yNzZSD] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][bslEQXHIfLPqB80gS7jW0lEbD6yNzZSD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][vJnGt8lHsJTop5UzNX86ZWBXMoNaEF7J] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][S8vI2kRD56gJqo53SH8huCGiJeI6wQKf] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ln4wj66U7JmdB6Fdvd5gw8cjSEU92UR8] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][htyIPrgjkuWfFK4j0fKLy6C4NDKF24Xn] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][lemQBRYQhIy4SnUcQha0E74Sr0ajLQsr] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][9A6HZhUb1pTpLXBhSrK4Yup0CN2z31HJ] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][vJnGt8lHsJTop5UzNX86ZWBXMoNaEF7J] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][fjK7kP1wf7CUKfMAdyiIOv29sjJSi9Pk] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][htyIPrgjkuWfFK4j0fKLy6C4NDKF24Xn] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][64wWeUxp4xFnje4aeg5Jdu9exs1d4Te6] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lemQBRYQhIy4SnUcQha0E74Sr0ajLQsr] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LCD5PexAESz0uOsYvAyLZqfllS1ACxrY] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][usYzfOUH5yeluXnSm0Bg0W7NL0tOpG2n] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fjK7kP1wf7CUKfMAdyiIOv29sjJSi9Pk] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][64wWeUxp4xFnje4aeg5Jdu9exs1d4Te6] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][LCD5PexAESz0uOsYvAyLZqfllS1ACxrY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][DjTmdQLULNBVRL6UNJ5TwmYcA72PDR4G] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][usYzfOUH5yeluXnSm0Bg0W7NL0tOpG2n] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][c3dFKrAzPmQWV7aE0sGW031lBViVmW9d] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][AdSRZ5YQ12FsrHvlrHfmMGuD6y9t9dBo] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][8YN6Kmc7Yi40JrhNI1ff85T8GvJUwAxr] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][DjTmdQLULNBVRL6UNJ5TwmYcA72PDR4G] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][c3dFKrAzPmQWV7aE0sGW031lBViVmW9d] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][AdSRZ5YQ12FsrHvlrHfmMGuD6y9t9dBo] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][H1E88Y1Ur7zwBjNToKAF5sgu1scqZOe6] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8YN6Kmc7Yi40JrhNI1ff85T8GvJUwAxr] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bnsAYs12eHSTUTYJcQ4DUzb3mowLkhe6] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TxgKl7mtv2AuaD1M9cTzlfRtcp15P370] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][yzimfKimNLk5JFAESAe2SbMDagOIqb0V] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][H1E88Y1Ur7zwBjNToKAF5sgu1scqZOe6] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][TxgKl7mtv2AuaD1M9cTzlfRtcp15P370] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qLwMv6x1njuLEdqqkgt8SPbsmhvYPUPK] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][bnsAYs12eHSTUTYJcQ4DUzb3mowLkhe6] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yzimfKimNLk5JFAESAe2SbMDagOIqb0V] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ID1xGKPmyzVGzGjIG0IJhDQI4ZPYVQ4E] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ieA272VeMXW1ko3a3mrYxyLyefmz71pv] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YBfMY5LW6o8TCj3OzpAQtxgTmUIg8FnD] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qLwMv6x1njuLEdqqkgt8SPbsmhvYPUPK] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][ID1xGKPmyzVGzGjIG0IJhDQI4ZPYVQ4E] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][oQ4gf1faf4No4VhBC1BkEEpbDTw0yfMC] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ieA272VeMXW1ko3a3mrYxyLyefmz71pv] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YBfMY5LW6o8TCj3OzpAQtxgTmUIg8FnD] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][G2kEpXj1Ym7FVtZ8F1X2VRg7Vf8k0AKu] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][uYDiy7epdso07XPRef5JfkST3ldSs2od] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][ZSQPKNYKJf1vZQFORwAs9Br4U11cCvRC] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][oQ4gf1faf4No4VhBC1BkEEpbDTw0yfMC] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][G2kEpXj1Ym7FVtZ8F1X2VRg7Vf8k0AKu] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][uYDiy7epdso07XPRef5JfkST3ldSs2od] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][XD8KQh3NrWD5G2tXRiEkIMueqvDVYlsU] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZSQPKNYKJf1vZQFORwAs9Br4U11cCvRC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][x3JfVYwAJI5Ni9aJKiUjjKjept03Bvzx] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qNmzOFTQQgAxE2Ab7ewUj12DMibvxr71] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][OoAQixs24GZzs0swgxqLfbv4gDEOh4lL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][XD8KQh3NrWD5G2tXRiEkIMueqvDVYlsU] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][x3JfVYwAJI5Ni9aJKiUjjKjept03Bvzx] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][qNmzOFTQQgAxE2Ab7ewUj12DMibvxr71] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][DOcNhSzTqU36N80xKWI5pEcHEThlfnOh] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][dNye9tQmFvrrcA29dRsbt8fYk2vgEssz] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OoAQixs24GZzs0swgxqLfbv4gDEOh4lL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][s4hfliUj8iJ9iHEVintgZ43brrS7h9z8] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][994yyxmuTAuZUpY3yfna5ZM3KmMmFjSi] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][DOcNhSzTqU36N80xKWI5pEcHEThlfnOh] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][s4hfliUj8iJ9iHEVintgZ43brrS7h9z8] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][dNye9tQmFvrrcA29dRsbt8fYk2vgEssz] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][V46DKX6V2E5udyB0lGlbvoOep2fSylgi] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][34JHFtEfTaVLVHQeamQh8pYoe3x6gKqt] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][994yyxmuTAuZUpY3yfna5ZM3KmMmFjSi] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][MyXW4Nyn5g1BIN5IBauyHrE80H1iS545] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][cP0XvD3asQ0vlAVwbwo2CaqWpwRjgJHI] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][V46DKX6V2E5udyB0lGlbvoOep2fSylgi] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][7RKYGe7B9trUUwRuflIqnVEYmHkkn4NH] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MyXW4Nyn5g1BIN5IBauyHrE80H1iS545] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][34JHFtEfTaVLVHQeamQh8pYoe3x6gKqt] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cP0XvD3asQ0vlAVwbwo2CaqWpwRjgJHI] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][v1lzqhW2MNwLHSvXJdLiW3blKqjrrY52] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tUEMdJJS1rIKO0iaFKGQZactOrmlJIwg] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][vkU7I0EqUw9j4xnWounKc6eCdd40Pqti] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7RKYGe7B9trUUwRuflIqnVEYmHkkn4NH] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ysWM5BXZTWBn0oDatRfOIGkBsHN1k4fL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][v1lzqhW2MNwLHSvXJdLiW3blKqjrrY52] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tUEMdJJS1rIKO0iaFKGQZactOrmlJIwg] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][vkU7I0EqUw9j4xnWounKc6eCdd40Pqti] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][CbbU4lVlra9Xv2dzgiRiOCy76HWXUwpk] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ysWM5BXZTWBn0oDatRfOIGkBsHN1k4fL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MAsnhckrQnGeS4ujpnPOXmP6Q1Sh4pnS] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ZDvdNGO0h6ft1vGFPwm9KkovKnyORcW9] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][OVUze4NMiyK1MPuYTJoSgq89FQ6HAk2A] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][CbbU4lVlra9Xv2dzgiRiOCy76HWXUwpk] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][MAsnhckrQnGeS4ujpnPOXmP6Q1Sh4pnS] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ZDvdNGO0h6ft1vGFPwm9KkovKnyORcW9] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][uv3oaw4dA8TVDGkzTydXjxr8mdrL59PV] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OVUze4NMiyK1MPuYTJoSgq89FQ6HAk2A] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][gjE3OoYTnKLEQaiN6VPB3UDKEDlBhiwP] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Cf2Gw5bIQWvNDVokBy3JjTLsXa3Lm0li] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IOFivMLGMfIzDOp8wBsX1NpU9Knd06di] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][uv3oaw4dA8TVDGkzTydXjxr8mdrL59PV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][gjE3OoYTnKLEQaiN6VPB3UDKEDlBhiwP] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Cf2Gw5bIQWvNDVokBy3JjTLsXa3Lm0li] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IOFivMLGMfIzDOp8wBsX1NpU9Knd06di] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][a6gNczNka5sPxzGfITJfNKOXFi4EYhui] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][RDoQzIRl9Q1aspKz5grDx09ay0uCiWO8] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wWoXdpn0n9B7396pqjqvqIBcJEpYlyDY] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ct1iA1vGHxITewvQxWoeasFXNFZ5V40G] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][RDoQzIRl9Q1aspKz5grDx09ay0uCiWO8] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][a6gNczNka5sPxzGfITJfNKOXFi4EYhui] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][wWoXdpn0n9B7396pqjqvqIBcJEpYlyDY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][V63KKl1K6E7gnpdEqCbDLxJsY2PCzAKA] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ct1iA1vGHxITewvQxWoeasFXNFZ5V40G] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ufBZeRncr10YwCOUGr7ueMZ4IqC8uu9I] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yqFMjqO4sHlFI97GNy0vbjzPzphPKidE] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][6JiFKlcspjF7j2XPlcBwja1u6PKUatfb] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][V63KKl1K6E7gnpdEqCbDLxJsY2PCzAKA] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ufBZeRncr10YwCOUGr7ueMZ4IqC8uu9I] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][yqFMjqO4sHlFI97GNy0vbjzPzphPKidE] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][egMH4spEmXG9jmgyV8JHizZ6P5frD1Ne] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6JiFKlcspjF7j2XPlcBwja1u6PKUatfb] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Wmgy27qMhrQ3g9qmZ0Pl7t9fXIAWipY3] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][s025TtaAakH4TPPNRrCN8KDH0w7au17C] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][myoQHqwdQmK2ZGyixVUD7Z8OVdaF3WsU] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][egMH4spEmXG9jmgyV8JHizZ6P5frD1Ne] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Wmgy27qMhrQ3g9qmZ0Pl7t9fXIAWipY3] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][s025TtaAakH4TPPNRrCN8KDH0w7au17C] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][myoQHqwdQmK2ZGyixVUD7Z8OVdaF3WsU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][voTUJHIf1mNldcmMPvKlGI8syntimSFb] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lWL8teAkwxyvmXYcSf449sTFGX1P60tb] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lmWgI0P9v3V73x3LHR6X34p0IdbXHOhm] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][p6Tlh8oHQPlbaRfLDrVVTw1CwVqT2fpc] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][voTUJHIf1mNldcmMPvKlGI8syntimSFb] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lWL8teAkwxyvmXYcSf449sTFGX1P60tb] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lmWgI0P9v3V73x3LHR6X34p0IdbXHOhm] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][p6Tlh8oHQPlbaRfLDrVVTw1CwVqT2fpc] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KM7UBoTCHhtlqq63TxAjlZdyxhvZoMBy] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Xv5LljuWWFVceCRLI6CuSTYjjjuGpoMu] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][z4H2xb8XfsyIkqEA0g9oDAjZ50ToaySl] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LXFN9eXuvjz5nijJ2KWs3ygQhkQOWfOC] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][KM7UBoTCHhtlqq63TxAjlZdyxhvZoMBy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Xv5LljuWWFVceCRLI6CuSTYjjjuGpoMu] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LXFN9eXuvjz5nijJ2KWs3ygQhkQOWfOC] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][z4H2xb8XfsyIkqEA0g9oDAjZ50ToaySl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][X9Iyi8VrymZ77ej9M1UsNHoDSPl5whA4] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yGnx51AcUnXAmOanvwvwUZXTBA7YoYvM] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][uOXEwUZ1eqE4hTzWosAGzjut6Di0eOAS] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][K3ibr1lEHTm4gbw6vtPWWoFyOeVyY2PR] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][X9Iyi8VrymZ77ej9M1UsNHoDSPl5whA4] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][yGnx51AcUnXAmOanvwvwUZXTBA7YoYvM] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][K3ibr1lEHTm4gbw6vtPWWoFyOeVyY2PR] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][uOXEwUZ1eqE4hTzWosAGzjut6Di0eOAS] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][6A8Yt59d7WqgfifrA8WYp7FbBqSKW5n1] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][a15pZMoxV8uiPCTE8qzv82JZegQ6aU4e] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][gkjj2XTvQsQ7C1nfYNM8ETtm0wspdvqU] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KzGvUH4W21joqzpQk5TMI9MawZPoswUq] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][6A8Yt59d7WqgfifrA8WYp7FbBqSKW5n1] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][gkjj2XTvQsQ7C1nfYNM8ETtm0wspdvqU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][a15pZMoxV8uiPCTE8qzv82JZegQ6aU4e] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KzGvUH4W21joqzpQk5TMI9MawZPoswUq] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][VxtTDGEMxHFs93NwOcvbgHuJbWxYBPu4] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hDuTl8BNVl09sbHvAr83gzV49z2cf7wM] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zzWLWl09Cw1CNwAn5725rfSq1AVIRG4P] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][edVyoYSVZE8MgK7xyytFRjVWXz7H8kLo] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][VxtTDGEMxHFs93NwOcvbgHuJbWxYBPu4] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][zzWLWl09Cw1CNwAn5725rfSq1AVIRG4P] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hDuTl8BNVl09sbHvAr83gzV49z2cf7wM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][edVyoYSVZE8MgK7xyytFRjVWXz7H8kLo] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][MmAQSI7WcRmbVMHUDP8XN3HZvRXnlUXm] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ggGNo1vPVs2zNzvg1dpIShHg4X2ITUUH] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][C0V1S9hlQU7iUgRWxI8gWOXQLdCI8qr0] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][NXVNx1vbpLbAXT3YkyxXSWOR8w6JKJnH] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][MmAQSI7WcRmbVMHUDP8XN3HZvRXnlUXm] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][C0V1S9hlQU7iUgRWxI8gWOXQLdCI8qr0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ggGNo1vPVs2zNzvg1dpIShHg4X2ITUUH] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GoC1f88ffQaqpH3xLA4kfG3SDeOT9bja] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][NXVNx1vbpLbAXT3YkyxXSWOR8w6JKJnH] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][7KFL9zxKjzt76D5IDHYHq2TIcYSZeR9x] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][skzEVDSahGxTpHTLIdSvVFQFo9fLyoJy] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Z36WbLa9StCJ77HzTvuo4A5Nnr1RrwzT] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][GoC1f88ffQaqpH3xLA4kfG3SDeOT9bja] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][7KFL9zxKjzt76D5IDHYHq2TIcYSZeR9x] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][P36lqjStCepLNMgAdLMkILO4jf7HsHmG] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Z36WbLa9StCJ77HzTvuo4A5Nnr1RrwzT] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][skzEVDSahGxTpHTLIdSvVFQFo9fLyoJy] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][1N4qlVMWosD2M323pJ2cAXsvoAOQeeVM] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][0B30BuQqWEH0wSGFZRr66Rc6mAOJbwqV] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8OBY7tsdjro0xkYdbVwTwNBtgDh9y8Nh] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][P36lqjStCepLNMgAdLMkILO4jf7HsHmG] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][1N4qlVMWosD2M323pJ2cAXsvoAOQeeVM] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][gFxotMP2pFUNg5FLt2KRrn2ZkgQwfvov] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][8OBY7tsdjro0xkYdbVwTwNBtgDh9y8Nh] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][0B30BuQqWEH0wSGFZRr66Rc6mAOJbwqV] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Qfh1uAly12d5v4b0HRhfmk7QqS4Todqw] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][b7tzUO1lY1becZEsYJWuIJXIAF1sRmMt] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wdfzdBGbtG6LsJzG2ISys0kSs3MbQQwj] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][gFxotMP2pFUNg5FLt2KRrn2ZkgQwfvov] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][DAhAcACasoaTYwX3AvE71r2FxtThkwVq] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Qfh1uAly12d5v4b0HRhfmk7QqS4Todqw] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][b7tzUO1lY1becZEsYJWuIJXIAF1sRmMt] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wdfzdBGbtG6LsJzG2ISys0kSs3MbQQwj] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Een2vMOHKahej4f9F68RROUEGBEY39yh] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][NevCFQf6CihmA2FuNDyunfSNwMTj93OX] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Cfjlcuho3xOXEXrwXTmLuNorydxvkZen] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DAhAcACasoaTYwX3AvE71r2FxtThkwVq] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][Een2vMOHKahej4f9F68RROUEGBEY39yh] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][rMd5L9znJyoxXI3ni7TPKgQxgXXSb57A] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][NevCFQf6CihmA2FuNDyunfSNwMTj93OX] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Cfjlcuho3xOXEXrwXTmLuNorydxvkZen] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][TOlV3LZ3SORE7xsB3Jr6cwd2aOBCYIbC] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][K00myRTad5gyZSIt5zUFowvB3UkBfj1p] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DWgS9QMSxou2I6tlbyPzr8B9QlAp4beL] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][rMd5L9znJyoxXI3ni7TPKgQxgXXSb57A] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][TOlV3LZ3SORE7xsB3Jr6cwd2aOBCYIbC] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][nHurldcWyLOjo17wr6FO8JD403juSQn8] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][K00myRTad5gyZSIt5zUFowvB3UkBfj1p] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][HeB8C8GcQV8dUYuGsCABLx5nXMVWbxzS] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][DWgS9QMSxou2I6tlbyPzr8B9QlAp4beL] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][LsBVnaGPDqhKVtq8AAemVR2cnmavf9h2] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][7IGSazhqWWyMVejdFa1JwbZmG06tADxN] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][nHurldcWyLOjo17wr6FO8JD403juSQn8] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][HeB8C8GcQV8dUYuGsCABLx5nXMVWbxzS] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][TQWdwvFebQePBaKJ194WtEQq93lsATXp] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][LsBVnaGPDqhKVtq8AAemVR2cnmavf9h2] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sC2uX5rDtoZ1kioK55SMeC6pxXyCmHae] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7IGSazhqWWyMVejdFa1JwbZmG06tADxN] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][U8y7sVEw6lFDQZ8gfqCBMxUVCBQTPg0v] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][s3gbmFNn1PRDQwVGZKsXG3kxx9qvzA0o] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][TQWdwvFebQePBaKJ194WtEQq93lsATXp] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][sC2uX5rDtoZ1kioK55SMeC6pxXyCmHae] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][U8y7sVEw6lFDQZ8gfqCBMxUVCBQTPg0v] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][7fVmIyOs1cFcURglfyyvF8KdEjDylMZI] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Fv4ZVkOWHIMXWXN4aquPXo12TZguihma] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][s3gbmFNn1PRDQwVGZKsXG3kxx9qvzA0o] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][a9rtcEUySA5AnaZRcC0wcZ0fI9zbyPpl] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][Q621rt4QsYdSfeVcQhGSUxuPC1W5VojY] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7fVmIyOs1cFcURglfyyvF8KdEjDylMZI] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][Fv4ZVkOWHIMXWXN4aquPXo12TZguihma] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][qJ6usqciZA3bVYO0kbfT9wsalcDl1m7P] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FihxaW1Kaw2RDdhG4ibKjTrfdkCFjAGk] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][a9rtcEUySA5AnaZRcC0wcZ0fI9zbyPpl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Q621rt4QsYdSfeVcQhGSUxuPC1W5VojY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][GWCxaX7o8TrhYrCg4WaY3xBz6J0iw8rm] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][0UNZLNiB8oV3wruMo1uhsQninh0kCXg8] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][qJ6usqciZA3bVYO0kbfT9wsalcDl1m7P] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][FihxaW1Kaw2RDdhG4ibKjTrfdkCFjAGk] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][rtwxbsaGAspFU9dG1Sh4A9vL7B31gLr6] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][GWCxaX7o8TrhYrCg4WaY3xBz6J0iw8rm] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][vkvAFXIvbhothNaGnAXHNelIO4kfQuPx] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][0UNZLNiB8oV3wruMo1uhsQninh0kCXg8] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][3NOWrxxm60gmhEHNT4DZHkU1iu5d4BvO] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][5nYcJfFM0qxNiYOxEtVhiCVzfumls8j9] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][rtwxbsaGAspFU9dG1Sh4A9vL7B31gLr6] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][vkvAFXIvbhothNaGnAXHNelIO4kfQuPx] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][b0Mbg0E2u0LY3LacWXcMwMstHr1ttUVj] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][b0Mbg0E2u0LY3LacWXcMwMstHr1ttUVj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][FrDduXus4wizdTnB8Eyl1GYXotUoKwch] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][FrDduXus4wizdTnB8Eyl1GYXotUoKwch] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][uJbE0jWksJwxJDiVjFgRPZUcTLtKIkFD] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][uJbE0jWksJwxJDiVjFgRPZUcTLtKIkFD] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][si9bf6cXBFNmS6z1u9UNDHm2k5MvS0l2] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][si9bf6cXBFNmS6z1u9UNDHm2k5MvS0l2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][R60fkAQ4cmWiwganNL3lJCZk9Pd7TxSG] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][R60fkAQ4cmWiwganNL3lJCZk9Pd7TxSG] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4hKp9UBIZtBzNRBs8izCy6QWskBKel49] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][4hKp9UBIZtBzNRBs8izCy6QWskBKel49] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LnabKa0OymWsLZr6rwufnl7HyWVCLbs6] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][LnabKa0OymWsLZr6rwufnl7HyWVCLbs6] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][XdtDPsyHgEkYSDM6FvY7aaDdiVU2vBuQ] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][XdtDPsyHgEkYSDM6FvY7aaDdiVU2vBuQ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Bu17uSiVHo59ESroYpd4QYPEjququ0Na] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Bu17uSiVHo59ESroYpd4QYPEjququ0Na] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][uG0FEiMXU5p2yJ1CGDdCd7MDMo4yvB53] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][uG0FEiMXU5p2yJ1CGDdCd7MDMo4yvB53] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5eyMOx5MICpSXMX7Ns5jpD1JdkQ7HZ1b] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][3NOWrxxm60gmhEHNT4DZHkU1iu5d4BvO] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][XXfb0V8x8ybwE5oWzwuBJ9L9p49iw3cP] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][XXfb0V8x8ybwE5oWzwuBJ9L9p49iw3cP] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bltHyvJFRU75OQdRTDmUnlUO9Y5r7roV] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][bltHyvJFRU75OQdRTDmUnlUO9Y5r7roV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][dNG0PY5HUK5C81jlh39EWOkGNbHZnTfx] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][dNG0PY5HUK5C81jlh39EWOkGNbHZnTfx] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ndjNn3HhvbjPtrrTBOYV9IbcwhtzUYnJ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ndjNn3HhvbjPtrrTBOYV9IbcwhtzUYnJ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Ox9DxC3mWOkcGTshckHWtnil9vLRWM6S] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][Ox9DxC3mWOkcGTshckHWtnil9vLRWM6S] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][HRvUxMH29vPkKunf2R0y03oxGrauPkfs] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][HRvUxMH29vPkKunf2R0y03oxGrauPkfs] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xSLdq59W45unwg8CoVg0Juy0MY6XrVRP] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][xSLdq59W45unwg8CoVg0Juy0MY6XrVRP] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][bwhaGKkv8mZiOJFBvmNveVWgfI2afezY] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][bwhaGKkv8mZiOJFBvmNveVWgfI2afezY] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Pq0ULi3a8Z5YU31A5aPed5LtxrAZidPg] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Pq0ULi3a8Z5YU31A5aPed5LtxrAZidPg] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LL2xLwfxDfkVmUdKVLJfBykVZVuZkHG3] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][LL2xLwfxDfkVmUdKVLJfBykVZVuZkHG3] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][5nYcJfFM0qxNiYOxEtVhiCVzfumls8j9] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][XMLlLdtiHnv3kCsoOTXpXsBNA1DMVZqW] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][XMLlLdtiHnv3kCsoOTXpXsBNA1DMVZqW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][x7TytPKYoTK1BkKx0wQqxLlLsQOtxVoM] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][x7TytPKYoTK1BkKx0wQqxLlLsQOtxVoM] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][yNebnrvw31otXYFVW4pKIHoQ6NXNJWAx] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][yNebnrvw31otXYFVW4pKIHoQ6NXNJWAx] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Vr8fVMqLvugloeZ89eX9bQB75mxsCG0d] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][Vr8fVMqLvugloeZ89eX9bQB75mxsCG0d] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][KwJ2rlML4Z0LucrpSs0lWD2tnxeIiNka] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][KwJ2rlML4Z0LucrpSs0lWD2tnxeIiNka] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LhPdmGbDQ2xhlLXlBFVUD4UgDNbvwyOt] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][LhPdmGbDQ2xhlLXlBFVUD4UgDNbvwyOt] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][28Ijy20ErIzJjjzO6YNG9ia3BWVOomqn] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][28Ijy20ErIzJjjzO6YNG9ia3BWVOomqn] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][aN4ZXvIaW3iuhBReMuzEkWlhPLaFQRNs] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][aN4ZXvIaW3iuhBReMuzEkWlhPLaFQRNs] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OwIavIS3sESbDPOGNb4zGt1IrVDgGnR1] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][OwIavIS3sESbDPOGNb4zGt1IrVDgGnR1] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2huakaVeQrF7kOFJS77VOBAzFfXywf8q] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][2huakaVeQrF7kOFJS77VOBAzFfXywf8q] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7IvUEC0JS9kasH8AJVFwUmZTwVOYl1B8] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lPSrH2jGTZfgUA9lImt3bLT48Y0dG7Hj] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][lPSrH2jGTZfgUA9lImt3bLT48Y0dG7Hj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Aiz6vCzCw7XCF0zoKHDDfcRP5w7IqD5J] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Aiz6vCzCw7XCF0zoKHDDfcRP5w7IqD5J] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QLF1umKOvEz6xdmhp3bB5aHS8gcVsyCo] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][QLF1umKOvEz6xdmhp3bB5aHS8gcVsyCo] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OeW9kvcCuYb6ADWlS3rXwUf7cPn2BFzp] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][OeW9kvcCuYb6ADWlS3rXwUf7cPn2BFzp] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fyj9AOqDF9XJTbvgDJSGvNCHRey1zCKi] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][fyj9AOqDF9XJTbvgDJSGvNCHRey1zCKi] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jpLqeJ2ZPujZ1HlJeSF8QOBqskfZJC9y] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][jpLqeJ2ZPujZ1HlJeSF8QOBqskfZJC9y] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][SFNPySdqCeYgAIyN48nc5dIHCkNx0O0A] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][SFNPySdqCeYgAIyN48nc5dIHCkNx0O0A] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][TfVKF3R7tFvESQz6ynFamKMM4uWf6Wlu] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][TfVKF3R7tFvESQz6ynFamKMM4uWf6Wlu] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fMs8ZHxqIRKiUvwzibGeXswhVeRwg3i8] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][fMs8ZHxqIRKiUvwzibGeXswhVeRwg3i8] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tgiyGkQ39rFxkM1pniiGn7oFL2beO4sD] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][tgiyGkQ39rFxkM1pniiGn7oFL2beO4sD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][o3I2IMgG6ens1qcq3IGA3Xm8KWgHMpKn] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][o3I2IMgG6ens1qcq3IGA3Xm8KWgHMpKn] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5eyMOx5MICpSXMX7Ns5jpD1JdkQ7HZ1b] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lnGEwldDHXneOWP4ePNHXhDwFGbdlbRi] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][7LI4pYsl04CSM8MX4GODnkVBNqRDOHZ7] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][foX2YTYHovimGGma7eRgW5vbWqnfo1su] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7IvUEC0JS9kasH8AJVFwUmZTwVOYl1B8] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][1Cd8V27POdGlg4Ia6Jw4MmGhrCxxIehE] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][7LI4pYsl04CSM8MX4GODnkVBNqRDOHZ7] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][lnGEwldDHXneOWP4ePNHXhDwFGbdlbRi] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][foX2YTYHovimGGma7eRgW5vbWqnfo1su] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][DZH8hf6wesV1NN8IZeBxvWcF3FTSKA0H] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][XsJdlPG57F7efrpk4f0Y9Wx7aMWRGmGW] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][1Cd8V27POdGlg4Ia6Jw4MmGhrCxxIehE] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OOD3sE9Odfl4JvO916P1rgnfrqFLinNk] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][RR1ndgSW71OZUTvt7BlVeqbDVsRTUXa8] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][DZH8hf6wesV1NN8IZeBxvWcF3FTSKA0H] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][XsJdlPG57F7efrpk4f0Y9Wx7aMWRGmGW] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][OOD3sE9Odfl4JvO916P1rgnfrqFLinNk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IIaCXdMYBllea01b4sh60AKVWgJufimL] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2iVpdo882S1aUdwlVvck5ejvvYr1KFre] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][RR1ndgSW71OZUTvt7BlVeqbDVsRTUXa8] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][todHRn4bxpBPIEy0rvCkPqyRi8BkT6zk] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][im4q9dV0nFoT4xIlDa3YSAeFwF2c5fQw] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][IIaCXdMYBllea01b4sh60AKVWgJufimL] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][2iVpdo882S1aUdwlVvck5ejvvYr1KFre] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][oRsJkEUI0FsprsByKeD1QBh8iSHxCOzX] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][todHRn4bxpBPIEy0rvCkPqyRi8BkT6zk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][K3AQyql4ZbSZznLfvvbyAzdRitBBNdps] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][im4q9dV0nFoT4xIlDa3YSAeFwF2c5fQw] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PlktgVWCgvbzPqRlOG4vXTgtidGzm8QP] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][VQjsejArEUrsxpFipBZTxcBHoC5HzHAw] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][oRsJkEUI0FsprsByKeD1QBh8iSHxCOzX] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][K3AQyql4ZbSZznLfvvbyAzdRitBBNdps] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][PlktgVWCgvbzPqRlOG4vXTgtidGzm8QP] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wEbRSVtqyvpPTT4YKxAj5Clcu70fDjoo] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fc2gHhh5ogmJGy7wwcijYc72Bl33O9aK] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][VQjsejArEUrsxpFipBZTxcBHoC5HzHAw] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][EQpx0BliexkYkqjtj5qJOrm3JPlWAj00] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][T6LL5PDzpnPGQteDy0pkDuQO4mEHc9d8] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][wEbRSVtqyvpPTT4YKxAj5Clcu70fDjoo] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fc2gHhh5ogmJGy7wwcijYc72Bl33O9aK] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][EQpx0BliexkYkqjtj5qJOrm3JPlWAj00] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][LGZl46zDktmB1CN5HagcK5Yoib60IoPY] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YOjsJXkaSBFIg5TKaIRFUblxjqq5dMRl] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3ojtOuLD3N8WZ346QRVeOYSSJW2m14Tf] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][T6LL5PDzpnPGQteDy0pkDuQO4mEHc9d8] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][PDrNHXYDVrPUXbYv1S8FpDLqVzhsfhru] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][LGZl46zDktmB1CN5HagcK5Yoib60IoPY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YOjsJXkaSBFIg5TKaIRFUblxjqq5dMRl] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3ojtOuLD3N8WZ346QRVeOYSSJW2m14Tf] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][sRzSfScgXQswVXrN6qXKRN8wQSehNuAo] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Ysf9RmSgngZHvJtbS94zSiov5DOxESUq] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][PDrNHXYDVrPUXbYv1S8FpDLqVzhsfhru] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][eHnjMy4ZDifTzUds1zj2Qx8UiyMxwVDH] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][ARjl1NKdbyBz16ivd9S6ULJxtX4YIxlq] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][sRzSfScgXQswVXrN6qXKRN8wQSehNuAo] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Ysf9RmSgngZHvJtbS94zSiov5DOxESUq] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eHnjMy4ZDifTzUds1zj2Qx8UiyMxwVDH] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][zRRflV481mxfGH2hlWHIkcjnvidTemVm] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cLDwkMFelhTqRGI4W1vK6bd5svgqAFu9] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ARjl1NKdbyBz16ivd9S6ULJxtX4YIxlq] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zeROsOEddKU7AjL5zY19wsdBJWbj1j4v] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][OjFo8SLmyJs7wltkQOsqDtVq9die0A8q] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][zRRflV481mxfGH2hlWHIkcjnvidTemVm] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cLDwkMFelhTqRGI4W1vK6bd5svgqAFu9] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][zeROsOEddKU7AjL5zY19wsdBJWbj1j4v] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][8ctxOrz36NtRCzzWuxYFiUFffpytT4My] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][Tw1p6ChSQCSjJqhzTeJl8uMyAGZuGSAy] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OjFo8SLmyJs7wltkQOsqDtVq9die0A8q] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2TbqtxcFnfhKFfbaYUKbBBqcM6cr8lKN] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][5zE4R3oVuQsSBZerbIx57Mjfh5faRgM2] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8ctxOrz36NtRCzzWuxYFiUFffpytT4My] Processed:  App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][Tw1p6ChSQCSjJqhzTeJl8uMyAGZuGSAy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2TbqtxcFnfhKFfbaYUKbBBqcM6cr8lKN] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pc214LW2z2IXhUuy1OLKJEg3yJOlZ77y] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][5zE4R3oVuQsSBZerbIx57Mjfh5faRgM2] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][bqfEw59XvCaqaFegTgfBVlDm0NcYOSqy] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xTDjyaLPLj8feJR1elKSgQb8sZObOA5y] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][jXJdtyFBZ44Ew8XZak1wIdDn0vef3Dqr] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][pc214LW2z2IXhUuy1OLKJEg3yJOlZ77y] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][bqfEw59XvCaqaFegTgfBVlDm0NcYOSqy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][xTDjyaLPLj8feJR1elKSgQb8sZObOA5y] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][4tgFSBL6RzTBAh6znBiZv5yRw66V7W4u] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][OG2MRl9uhOJ587KktUrcqxFZ5zOWQXp2] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jXJdtyFBZ44Ew8XZak1wIdDn0vef3Dqr] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][GUVhzYMxve1JxCpA2sxhBwDn7y3W8b1Z] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][MCAzMpKSYFR5dJwGEhA30k0BSAmt4S8V] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4tgFSBL6RzTBAh6znBiZv5yRw66V7W4u] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][oku269JGjAt4yYkxOpzVSsGbfvA06Zap] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][OG2MRl9uhOJ587KktUrcqxFZ5zOWQXp2] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][oku269JGjAt4yYkxOpzVSsGbfvA06Zap] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][GUVhzYMxve1JxCpA2sxhBwDn7y3W8b1Z] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][c3pQy0pPJS5EW9YxbCEwUCRZ3P6fmnIZ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][MCAzMpKSYFR5dJwGEhA30k0BSAmt4S8V] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zHf53RDARuibYkln9XUhddntnXPMxiXa] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][rbIHhuwa4Vx7m0BmcKbT7bMj5tkngvK1] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6AV4LFGFVvPnOBAa9jMhISyiAwozstXc] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][c3pQy0pPJS5EW9YxbCEwUCRZ3P6fmnIZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zHf53RDARuibYkln9XUhddntnXPMxiXa] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][rbIHhuwa4Vx7m0BmcKbT7bMj5tkngvK1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][6AV4LFGFVvPnOBAa9jMhISyiAwozstXc] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][GNAoz7AozCwWvJ88okZ2YA4NyQ6LVb4h] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IIMEg8KRZaTeFUwVQf6OzjesXyCpdvXE] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HPfoM4iFB0vy4T8tcaWjfRc0Pxoc7HM0] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][QFjgws7p5R2bHUh7dFChMM0gyno5Wwga] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][GNAoz7AozCwWvJ88okZ2YA4NyQ6LVb4h] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][IIMEg8KRZaTeFUwVQf6OzjesXyCpdvXE] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HPfoM4iFB0vy4T8tcaWjfRc0Pxoc7HM0] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][QFjgws7p5R2bHUh7dFChMM0gyno5Wwga] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][HPUVP26tf5XShVi48YYW3VCzOc9vRnx2] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fQT8UohEYzuyl4gFydolczYjQtEFEwGo] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8z0e48CyDu0THfmUdMdowErErbj7r0Bb] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LnFm4nZ3Mas7So9GCtKanoPc7rGxm7mc] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][HPUVP26tf5XShVi48YYW3VCzOc9vRnx2] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][fQT8UohEYzuyl4gFydolczYjQtEFEwGo] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8z0e48CyDu0THfmUdMdowErErbj7r0Bb] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][LnFm4nZ3Mas7So9GCtKanoPc7rGxm7mc] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][iF2TzWudMzhpIjZq467LcwKqVVJq1pw3] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][AaEuKTyfFEhJ18w70Plx5JBnqGtdhx0v] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wqyPIzh2EP4VKMrCT8sy3luq6rpzw22O] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sWMb75MZOKlnSbc70KHkuPvk1KW7iTDW] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][iF2TzWudMzhpIjZq467LcwKqVVJq1pw3] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][AaEuKTyfFEhJ18w70Plx5JBnqGtdhx0v] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wqyPIzh2EP4VKMrCT8sy3luq6rpzw22O] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sWMb75MZOKlnSbc70KHkuPvk1KW7iTDW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mAgZsZLgth8gqCzPIqUDJhCzw0oqgRot] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Fl2XT91348cSTxvyzq2U2O4cluZN56It] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][iXxEX4r3z3Lnl5CmDuGr5ZGn0Ngx7Zs4] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qPkzPVeWr75GJ9blHplZPsU5mwPGOsDD] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Fl2XT91348cSTxvyzq2U2O4cluZN56It] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mAgZsZLgth8gqCzPIqUDJhCzw0oqgRot] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qPkzPVeWr75GJ9blHplZPsU5mwPGOsDD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][iXxEX4r3z3Lnl5CmDuGr5ZGn0Ngx7Zs4] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ogrLwwhPeitFo9dtf0cTZGVnfo4UZbJH] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ll6CUzuTXqD8Nyv5ZFDx1c2pIPB35QFy] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][X3wn5tTCak5BM500byYoWAyVLfumFP2d] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZP9NDGq23yvw4rvol7rmnZRhZVBFPTgU] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][ogrLwwhPeitFo9dtf0cTZGVnfo4UZbJH] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][ll6CUzuTXqD8Nyv5ZFDx1c2pIPB35QFy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][X3wn5tTCak5BM500byYoWAyVLfumFP2d] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][aVrs4Buz5qkj02h1DwZfcCV4qcHPvpfl] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ZP9NDGq23yvw4rvol7rmnZRhZVBFPTgU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YivwwKYT2itozc825xKqHyzDzIjIc9ui] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wf97chOIFGtqLTgwDZFDKD0J7TIw5MLr] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][AK4hjzq4F8YGYQ3ZmMZM8DOA8EFdT2Ev] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][aVrs4Buz5qkj02h1DwZfcCV4qcHPvpfl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][YivwwKYT2itozc825xKqHyzDzIjIc9ui] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][wf97chOIFGtqLTgwDZFDKD0J7TIw5MLr] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][5RNe5wD8d4wmeuFUBkHEagD69obM4TR9] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Ej0lNMPpz33X69x5GcpVp0NeXpDySItk] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1CMX3zhBrUtMWtkFwgpZNKid6SG2SvJ8] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][AK4hjzq4F8YGYQ3ZmMZM8DOA8EFdT2Ev] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][v31x4yrMfOSAwER8eVvcvVVh1RIDPoec] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][5RNe5wD8d4wmeuFUBkHEagD69obM4TR9] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][1CMX3zhBrUtMWtkFwgpZNKid6SG2SvJ8] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][v31x4yrMfOSAwER8eVvcvVVh1RIDPoec] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Ej0lNMPpz33X69x5GcpVp0NeXpDySItk] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][l0iByTIgtQNTRgQGgSfoQqTVbdkdoGFT] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][PigeWyQLdiCgS0M8Tcw4skMuVN4DrjJg] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4UTuLNdWWESYIue5PpqPtz49OcGUav2D] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][axW7PzUC1iTEgGrimtuUxofVuA4nruR4] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][PigeWyQLdiCgS0M8Tcw4skMuVN4DrjJg] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4UTuLNdWWESYIue5PpqPtz49OcGUav2D] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][l0iByTIgtQNTRgQGgSfoQqTVbdkdoGFT] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][axW7PzUC1iTEgGrimtuUxofVuA4nruR4] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][yOEw2eRUz9flQdvwJrKnEmbXUJXbEYwC] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][mO68u9i4huD9Pvqu6vAoeRMrT6iAwJx1] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ktCrx7HeoS4RuTPK5CymKKI8zQafIVHf] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HAymgasKybkQvl2FkkjA2jbcZF5LolHS] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][yOEw2eRUz9flQdvwJrKnEmbXUJXbEYwC] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][mO68u9i4huD9Pvqu6vAoeRMrT6iAwJx1] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ktCrx7HeoS4RuTPK5CymKKI8zQafIVHf] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HAymgasKybkQvl2FkkjA2jbcZF5LolHS] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][1tl8C11sfDHAAeNjUbTNrFpL9OS8HCPy] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][EFqIbhyiM838kOKsgLj1kvH5fZbeHPZ7] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][jqSHszesuJ1MInKGdDTPWhZ0uDYZ9jOU] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][swtpm6eG7NPkHp6z6hR1Eh6zFKuD61N5] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][EFqIbhyiM838kOKsgLj1kvH5fZbeHPZ7] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][1tl8C11sfDHAAeNjUbTNrFpL9OS8HCPy] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][swtpm6eG7NPkHp6z6hR1Eh6zFKuD61N5] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][jqSHszesuJ1MInKGdDTPWhZ0uDYZ9jOU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][oLR34SzJI7IZXEr9aodMi6xhn67ONoRZ] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][rNWvZuTz3prcctEzThcl8sAOgwGQPHUX] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][URJWIseR09yZ8lT6OLczr6Sj4JhNuQjL] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Cn9EETM00WJn2NPssKDV75dop4xpjbeW] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][oLR34SzJI7IZXEr9aodMi6xhn67ONoRZ] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][rNWvZuTz3prcctEzThcl8sAOgwGQPHUX] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][URJWIseR09yZ8lT6OLczr6Sj4JhNuQjL] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Cn9EETM00WJn2NPssKDV75dop4xpjbeW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fa3EkIjRFQ1OoHhUz0BGvOPMxTm2ncXY] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][AGcz92zBBqRr7xExypBaFvDtHcAHH9YU] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4lBFxvYK0M7cZBHTorRcOpFpwTEQiiQE] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][0YcGERIdeORwxq9z5RSntj5ore9MELki] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][AGcz92zBBqRr7xExypBaFvDtHcAHH9YU] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][fa3EkIjRFQ1OoHhUz0BGvOPMxTm2ncXY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4lBFxvYK0M7cZBHTorRcOpFpwTEQiiQE] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][0YcGERIdeORwxq9z5RSntj5ore9MELki] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][sNBeCtQgyv8Vy8kilTknZtaKCqTm4PCD] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ulcOXQQEvL6XN0AIZuNOVGS9XtfKuPT7] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][plt1YmLMo8CATZ0SwSevsnBACZZqcuKa] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][B9pPojtEIdwyg2tQ3FGmPHQ9ZxePjqch] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][sNBeCtQgyv8Vy8kilTknZtaKCqTm4PCD] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][ulcOXQQEvL6XN0AIZuNOVGS9XtfKuPT7] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][plt1YmLMo8CATZ0SwSevsnBACZZqcuKa] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][B9pPojtEIdwyg2tQ3FGmPHQ9ZxePjqch] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][WYZ3HRVMkaiqEDW1XZWbXHuHeoSxlv5n] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wu9FcQFSZ6qo0NWTfkpTCQENylvnlo6F] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][10Fw2VAr5kiPyQ5foQvopp1av4Z9rkpi] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][DK243Ia9MPVcaDyUyjrKtCPwR8FmxALl] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][WYZ3HRVMkaiqEDW1XZWbXHuHeoSxlv5n] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][wu9FcQFSZ6qo0NWTfkpTCQENylvnlo6F] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][10Fw2VAr5kiPyQ5foQvopp1av4Z9rkpi] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][DK243Ia9MPVcaDyUyjrKtCPwR8FmxALl] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xwWAME9RGJly5U8jq3xfMBDZHwt1VBwQ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ifhN4hmq01a8UGphVP3iE9iKO1T1O8T3] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][W3AVeWwq1xKkIF1Hs7hsVtehQWHLccwf] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][eKVMjSrNrBYo1WTLJoGCBnDpSlJVsCRH] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][xwWAME9RGJly5U8jq3xfMBDZHwt1VBwQ] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ifhN4hmq01a8UGphVP3iE9iKO1T1O8T3] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][eKVMjSrNrBYo1WTLJoGCBnDpSlJVsCRH] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][W3AVeWwq1xKkIF1Hs7hsVtehQWHLccwf] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][JdrEEphXzMTpqrVxIcPEyDc5sQ3Imwvf] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][mzcWcqJfWPARWqNfVp0BpZonsRAdl9eB] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hYAwYtBEZj2K83estMIeLwqfPE21KlRK] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][E1cEgjaI9DHBZHfPnAG8F7QPKzALWBXQ] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][JdrEEphXzMTpqrVxIcPEyDc5sQ3Imwvf] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][mzcWcqJfWPARWqNfVp0BpZonsRAdl9eB] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][E1cEgjaI9DHBZHfPnAG8F7QPKzALWBXQ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YraYVlAts0dmF1LZKRxy1vhC8ZHkSNX1] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hYAwYtBEZj2K83estMIeLwqfPE21KlRK] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][iFoqUKrXe4SL8ez46YteMDTOeVbHLflJ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][e2kMPH4EkvnYXMGYzBLm4dC1zadAfqBN] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][o4dJfa3TTFoKoXBmcdATbpyz8TdSlBPm] Processing: App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][YraYVlAts0dmF1LZKRxy1vhC8ZHkSNX1] Processed:  App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][iFoqUKrXe4SL8ez46YteMDTOeVbHLflJ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3G2jhOJnyNIcECaGGmE79vlTNfs6rKvZ] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][e2kMPH4EkvnYXMGYzBLm4dC1zadAfqBN] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mw2zFeZW8KFHIzAMCgbMgLVBYtUFLU4F] Processing: App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][o4dJfa3TTFoKoXBmcdATbpyz8TdSlBPm] Processed:  App\Jobs\ReadExpectRead
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][dZUDMoa3ZP7dXqFseswmb9850b9mB5za] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][LoH6XO2ha8RXHhnFDMwFyPEQQzWFo4CA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3G2jhOJnyNIcECaGGmE79vlTNfs6rKvZ] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][LoH6XO2ha8RXHhnFDMwFyPEQQzWFo4CA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8Vw0UlLwi3noWAAPnvZeNhd3Jyi7VbWJ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8Vw0UlLwi3noWAAPnvZeNhd3Jyi7VbWJ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YxlfPMimMHYdkH2TJ1U6Z52iTcsPMFUW] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][YxlfPMimMHYdkH2TJ1U6Z52iTcsPMFUW] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][73BwhwYioVjLQz2qmpMs9z4Es3fLne9B] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][73BwhwYioVjLQz2qmpMs9z4Es3fLne9B] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YzUP9NrrCypjJqvW2NxXExGKikxKwNgD] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][YzUP9NrrCypjJqvW2NxXExGKikxKwNgD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][tAjhFoJ3j6nXqV7QqlqUIJWbB4A4AN9Y] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][tAjhFoJ3j6nXqV7QqlqUIJWbB4A4AN9Y] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][odFP4wcZhnS28sxwJlHKFfiiy6AySP6g] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][odFP4wcZhnS28sxwJlHKFfiiy6AySP6g] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][yZV5uq4b5ENDnxl8WQHfwJvfWmI5UNRi] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][yZV5uq4b5ENDnxl8WQHfwJvfWmI5UNRi] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][zE7m72jz0Mpb0aMnijPTHJoy8D4zHrHO] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][zE7m72jz0Mpb0aMnijPTHJoy8D4zHrHO] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][sw31gE4zsGmVPWE2zifhosdugWAq55bL] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][sw31gE4zsGmVPWE2zifhosdugWAq55bL] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][sLX0tsqb3GlWmxXC1Ym9nio5UVmNCDvn] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][sLX0tsqb3GlWmxXC1Ym9nio5UVmNCDvn] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hEHgS0uRQXnpe9Lmqw2Yllf1TPkbRpRE] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][CeiGM43xVYnca7mTm11ENKOfmTdO89fj] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][CeiGM43xVYnca7mTm11ENKOfmTdO89fj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][i35rjNmXuhbuMBTOlULGhzjWE0Uds2OL] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][i35rjNmXuhbuMBTOlULGhzjWE0Uds2OL] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][QgFB6KSayXFlOqpiQ2aOH1dlX6FbF5Cs] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][QgFB6KSayXFlOqpiQ2aOH1dlX6FbF5Cs] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][bz4fDxNwnXV8zYBRkY1QNNp8EjonMTDY] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][bz4fDxNwnXV8zYBRkY1QNNp8EjonMTDY] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][45NCRvfEHmZYaf73mRgcHK2pnROXJNog] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][45NCRvfEHmZYaf73mRgcHK2pnROXJNog] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][CrpVHVmjc0wOoEi4xBGBTTdpwSqKducV] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][CrpVHVmjc0wOoEi4xBGBTTdpwSqKducV] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][2rhR6UOCQ6dA1XtRinofl0ns5hShxsfo] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][2rhR6UOCQ6dA1XtRinofl0ns5hShxsfo] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][7HXLm6pyMdS7c7VFYjeREanKlf03j5Ha] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][7HXLm6pyMdS7c7VFYjeREanKlf03j5Ha] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QMnlzSZm8MgxIJZAmogRkizrfcg1O7p2] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][QMnlzSZm8MgxIJZAmogRkizrfcg1O7p2] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][UzQ9piozryZDHuWRNc5FM7jpGdTW8qYY] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][UzQ9piozryZDHuWRNc5FM7jpGdTW8qYY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][kyHCvLDx18zb1D0pamuoLpzwME5YZA4i] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][mw2zFeZW8KFHIzAMCgbMgLVBYtUFLU4F] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][tRYJMhn5pvlMrO0u6oUNMRsn17avTv9H] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][tRYJMhn5pvlMrO0u6oUNMRsn17avTv9H] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][pA1zb4EfMadjhqTw9lCQFA5uBmCWlqvn] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][pA1zb4EfMadjhqTw9lCQFA5uBmCWlqvn] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][cI4fFJNkxDE7un3m7jzGd1cAautmocrU] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][cI4fFJNkxDE7un3m7jzGd1cAautmocrU] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][ZPJKRYtjVHc0aywL9UBFJI4W2NVnCYaB] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][ZPJKRYtjVHc0aywL9UBFJI4W2NVnCYaB] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][leUD3hKEpXXiMBO4gluFJcSDV4HkpSsT] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][leUD3hKEpXXiMBO4gluFJcSDV4HkpSsT] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Ma4J3eTbfN4qto7EibMZME6uyxLjrlJ3] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][Ma4J3eTbfN4qto7EibMZME6uyxLjrlJ3] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][tKc7Vix0tRaiWEgAp6UFrwTQ1bCR10D9] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][tKc7Vix0tRaiWEgAp6UFrwTQ1bCR10D9] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YY5vRz1o3Qz3yBcH5qEHosIeQBLzha7C] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][YY5vRz1o3Qz3yBcH5qEHosIeQBLzha7C] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qQMkqVAEe9wGoetYD0RjvGdRSGFpMiOW] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][qQMkqVAEe9wGoetYD0RjvGdRSGFpMiOW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7HI3LruLPJxDeEAtaxbX123wRbTav2qS] Processing: App\Jobs\ReadExpectWrite
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][dZUDMoa3ZP7dXqFseswmb9850b9mB5za] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][DeIUgryQ1jyu5DTMxS8nLRcFfQWooQGl] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][DeIUgryQ1jyu5DTMxS8nLRcFfQWooQGl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][7SoWvudgYrmWX22BpH6NHrmaaSNVgTDh] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][7SoWvudgYrmWX22BpH6NHrmaaSNVgTDh] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][zru6rDycTKFXArIBEMevikAmb9DorLdl] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][zru6rDycTKFXArIBEMevikAmb9DorLdl] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][mMwplN0fA8c0zjuHaKAZlNNK52DhOeFb] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][mMwplN0fA8c0zjuHaKAZlNNK52DhOeFb] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][4vHBG2HIKGTrFDtN7aRj7JDOES2Ajj0J] Processing: App\Jobs\ReadExpectRead
"FAILURE!!! write PDO after read action"
[2021-06-11 12:20:32][4vHBG2HIKGTrFDtN7aRj7JDOES2Ajj0J] Processed:  App\Jobs\ReadExpectRead
[2021-06-11 12:20:32][hN1r5c2iJpnxz6Ti4v6AMvo2pixhJL3h] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][hN1r5c2iJpnxz6Ti4v6AMvo2pixhJL3h] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HKRYZxlwkmEq3lSp7ySgkKO0Vcdi7xJ4] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][HKRYZxlwkmEq3lSp7ySgkKO0Vcdi7xJ4] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][WVQiUdqyClIHzzigHSzlT1jPtqsIyNhh] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][WVQiUdqyClIHzzigHSzlT1jPtqsIyNhh] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][PxXnAziHG82wiJRPah9X2nJyYUTWTDTC] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][PxXnAziHG82wiJRPah9X2nJyYUTWTDTC] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][SZFYrjQrohCIkNHIfeJAD2fFwt3aNfiw] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][kyHCvLDx18zb1D0pamuoLpzwME5YZA4i] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][7HI3LruLPJxDeEAtaxbX123wRbTav2qS] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][SZFYrjQrohCIkNHIfeJAD2fFwt3aNfiw] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][hEHgS0uRQXnpe9Lmqw2Yllf1TPkbRpRE] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][x9wCWsllD6daKTw1LhZg156p6n2K5zcg] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KnNbejPPrrYUppCpacLayl9skM9PmsW2] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Rmv2So4Q8w1RaPdQGKkDmRDT16eL0jx3] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][1ys62hbHUHQqtudeT1WU687Xf3ESNFol] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][x9wCWsllD6daKTw1LhZg156p6n2K5zcg] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KnNbejPPrrYUppCpacLayl9skM9PmsW2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Rmv2So4Q8w1RaPdQGKkDmRDT16eL0jx3] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][OiwDyavWwMeWkd6AFCOHiQ5upMreQeup] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8rMwpxR6bdcoeJznE4hsL4eWUWBHiN4T] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][1ys62hbHUHQqtudeT1WU687Xf3ESNFol] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][Hk2HCeyQVAEBWI2W4efa44ozuC26sk5o] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Z1Uj8VY6I5RFkkKwnzzO00UBtDBA2kDo] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OiwDyavWwMeWkd6AFCOHiQ5upMreQeup] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][phPufHOFNxaWtrG6SD9kIDAid6rp62mf] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][Z1Uj8VY6I5RFkkKwnzzO00UBtDBA2kDo] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8rMwpxR6bdcoeJznE4hsL4eWUWBHiN4T] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][Hk2HCeyQVAEBWI2W4efa44ozuC26sk5o] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][aXPEDZkKPjXq52cghxu6p7dExMc5nORL] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][phPufHOFNxaWtrG6SD9kIDAid6rp62mf] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][0BbZpUO9DUT1SgFEnh2sPGUopfkRFmQt] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wIHH9KTgQ9MLgE4bX7gMkB72LTO6yz6A] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][w3Ft1d6VQThw3GFRpMvI2pMIBMRjRqXt] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][0BbZpUO9DUT1SgFEnh2sPGUopfkRFmQt] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][aXPEDZkKPjXq52cghxu6p7dExMc5nORL] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][w3Ft1d6VQThw3GFRpMvI2pMIBMRjRqXt] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jxiOaYw7UWlAdMwl9WhmE660ramRGXbV] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][wIHH9KTgQ9MLgE4bX7gMkB72LTO6yz6A] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][wWBpQaHHmvM3EJ7kbUlPnjZI6cFoDV3k] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KtyzQTgmQLbDEw6Bipk6OdM6orf4JIxp] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wmlGIkvLisreEec94kXites2gNtBvh2m] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][wWBpQaHHmvM3EJ7kbUlPnjZI6cFoDV3k] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KtyzQTgmQLbDEw6Bipk6OdM6orf4JIxp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wmlGIkvLisreEec94kXites2gNtBvh2m] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jxiOaYw7UWlAdMwl9WhmE660ramRGXbV] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][jurmDqdmdUzTG5reUVijiBNa432FwjiI] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][3KCqKtGJIwCpFRurNY0P7IgUeWKT3wSc] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][zq7jqilZQpKpbIH2vC4S92PkzPBGlAoN] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][SkdbYrdHGRdPOiC1RVuMbWuFLOk7yugl] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][3KCqKtGJIwCpFRurNY0P7IgUeWKT3wSc] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][SkdbYrdHGRdPOiC1RVuMbWuFLOk7yugl] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][zq7jqilZQpKpbIH2vC4S92PkzPBGlAoN] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][jurmDqdmdUzTG5reUVijiBNa432FwjiI] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][PACjp7Wm4ImpnGiUh58i6KGXClQRpsI2] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][H59xBSdqiuNxd9dSR66GKaAy8BII0MqU] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][PesshDxZibgfXVpIWx1wBeSYyAJ0Kl9w] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][eTHsPYAdbcp4ne06yx03dNVpeC773jB3] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][PACjp7Wm4ImpnGiUh58i6KGXClQRpsI2] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][ippWJnYH62tFxpAcKwWEkWFnM1lMpqYY] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][H59xBSdqiuNxd9dSR66GKaAy8BII0MqU] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][eTHsPYAdbcp4ne06yx03dNVpeC773jB3] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][PesshDxZibgfXVpIWx1wBeSYyAJ0Kl9w] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][10ZsGPVp6BJhJjK6Z8HIIH5cY4YiRg8n] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][qhUUY8ZOryuZHjCM0VHD0kQnDxWG9sWd] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][dmy0vyUEvkmpeTBbKqXtvS2eBayULpRm] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ippWJnYH62tFxpAcKwWEkWFnM1lMpqYY] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][sv4qMhlcgrIUuIDwQuzzjsykpA6eGM3v] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][qhUUY8ZOryuZHjCM0VHD0kQnDxWG9sWd] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][10ZsGPVp6BJhJjK6Z8HIIH5cY4YiRg8n] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][dmy0vyUEvkmpeTBbKqXtvS2eBayULpRm] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][5CItpEqcsPLwVVLjXekJyO0Ow30AjiRo] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][VS1sIlSUPxF6uzn2DX1Br0T7DoXoTFoj] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZbrJMVuH4D4DgyBUboOz28CDv543qJu9] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][sv4qMhlcgrIUuIDwQuzzjsykpA6eGM3v] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][3Euumi0TJc606dSTcvVl2HItYt4QJX0g] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][VS1sIlSUPxF6uzn2DX1Br0T7DoXoTFoj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZbrJMVuH4D4DgyBUboOz28CDv543qJu9] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][5CItpEqcsPLwVVLjXekJyO0Ow30AjiRo] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][6ItEgUQLuwYSnR976MsjaENFcb4T4wKT] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][jiBLtdQ8jy5u6o0qjjeW17IxAClZDm3L] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][vvedtW9Gzo6d8hN6w5Z26v1ih76w3MP2] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][3Euumi0TJc606dSTcvVl2HItYt4QJX0g] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][jiBLtdQ8jy5u6o0qjjeW17IxAClZDm3L] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xStYCjDrIR1KoG48vhp580U3yDESwUfy] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][A3uArSWPPhJtgbCko5cbCE4oo1PASoNJ] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][6ItEgUQLuwYSnR976MsjaENFcb4T4wKT] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][vvedtW9Gzo6d8hN6w5Z26v1ih76w3MP2] Processed:  App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][d1NfCreMdP7vyGOEN9KFualRyiDGxr8Q] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][cUDpO7rEbctbY6ug5davIAtFaJUUgugQ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xStYCjDrIR1KoG48vhp580U3yDESwUfy] Processed:  App\Jobs\WriteExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][A3uArSWPPhJtgbCko5cbCE4oo1PASoNJ] Processed:  App\Jobs\WriteExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][DmtYQJzkpKFhBfXSMKZLV6WZDuHY5mnP] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][d1NfCreMdP7vyGOEN9KFualRyiDGxr8Q] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][cUDpO7rEbctbY6ug5davIAtFaJUUgugQ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][tknd7iDIXS959v19b0GLicEZSeMkhSJU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][aWTzf6xndCZYerLxqycl4Y1feTlePL2T] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][ZDLB4VPg4Z5aVt0UuNRBFffZQ1N2rayY] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][DmtYQJzkpKFhBfXSMKZLV6WZDuHY5mnP] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][tknd7iDIXS959v19b0GLicEZSeMkhSJU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZDLB4VPg4Z5aVt0UuNRBFffZQ1N2rayY] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][wk40hAAttyUsHtGxvwuyHM7Zz4MYfGsy] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OmhEnQHncNoTOhOlHZUgzPJBZSCKAA2V] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Y533KshOJorp9TRTZ0oNBbreVS6oO4nY] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][aWTzf6xndCZYerLxqycl4Y1feTlePL2T] Processed:  App\Jobs\WriteExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][lL8I9oY7dXbFLRW4ox8emkqs5PFNENNi] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][wk40hAAttyUsHtGxvwuyHM7Zz4MYfGsy] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OmhEnQHncNoTOhOlHZUgzPJBZSCKAA2V] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][07jYVVOBt3DEPkkMNcHgCYZLawpmhnMn] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8Uzq8JTiUQpyPKFSHPTuFZ4aqe8wmHFN] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Y533KshOJorp9TRTZ0oNBbreVS6oO4nY] Processed:  App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][7SaFM9bwA8ekXbOgfzswxoW2JwpdpYfP] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lL8I9oY7dXbFLRW4ox8emkqs5PFNENNi] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][07jYVVOBt3DEPkkMNcHgCYZLawpmhnMn] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8Uzq8JTiUQpyPKFSHPTuFZ4aqe8wmHFN] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][SaVrSeWbxCAk0OHvStGJqgRNas3As8Fi] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][3j0N6jlDjoE9bXg0PvqKlO8tRFXafKlR] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][2fOkCBQ46of4GIoCzQ56rhujtwdgUBgk] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7SaFM9bwA8ekXbOgfzswxoW2JwpdpYfP] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][5kAqoS85L2lBUHtplMuwaIeg3uedtHvq] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][2fOkCBQ46of4GIoCzQ56rhujtwdgUBgk] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][SaVrSeWbxCAk0OHvStGJqgRNas3As8Fi] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][3j0N6jlDjoE9bXg0PvqKlO8tRFXafKlR] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][gEVBiVn4oGmZbgbKE4B0n1e9BrSCUI4c] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][XpnVLL2PucCmkD07pu3scogfhYwo8GBj] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][WaiELmFC03YbHEeeP01al6Q0l3Lv5msF] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][5kAqoS85L2lBUHtplMuwaIeg3uedtHvq] Processed:  App\Jobs\WriteExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][XpnVLL2PucCmkD07pu3scogfhYwo8GBj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][IuuExfdN5IRztARXW4rUqPtOJB69cTki] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][WaiELmFC03YbHEeeP01al6Q0l3Lv5msF] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][gEVBiVn4oGmZbgbKE4B0n1e9BrSCUI4c] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][EPc7Wn7eUKfLwZRfBZ1i9Lcm5iQFCCy9] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Bp8VmOrxwRr0zYsg4YKP3zHxbGU7GOfK] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jFs5vlEY0QG5JP46KFxP1o5MdN1G2I5W] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][IuuExfdN5IRztARXW4rUqPtOJB69cTki] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][gURuZtAoMwKsHSK7yMOxK0Qz5GCi42Ll] Processing: App\Jobs\WriteExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][Bp8VmOrxwRr0zYsg4YKP3zHxbGU7GOfK] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][EPc7Wn7eUKfLwZRfBZ1i9Lcm5iQFCCy9] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][lf9vgpbpeb4jCmKO0eUa63z2TXxhPDI5] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jFs5vlEY0QG5JP46KFxP1o5MdN1G2I5W] Processed:  App\Jobs\WriteExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][zWAuYjsLvVcE6lcndnJyQmkf6vegZjy8] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][IuVvszg2XF4MwdVdP5bdzUwfraSJ4O6d] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][gURuZtAoMwKsHSK7yMOxK0Qz5GCi42Ll] Processed:  App\Jobs\WriteExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][lf9vgpbpeb4jCmKO0eUa63z2TXxhPDI5] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][1ERgfquZNPLrrqFzJ4S3cweOBP4Y3tnb] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][6Olg1vz9qE8yJrlzwc5ssjZEJvFcgkz8] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][IuVvszg2XF4MwdVdP5bdzUwfraSJ4O6d] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][zWAuYjsLvVcE6lcndnJyQmkf6vegZjy8] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][1ERgfquZNPLrrqFzJ4S3cweOBP4Y3tnb] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fRSN50DmHzYzrzcosMpSWMYoeoIFSde1] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][egaEuJgOIYmNrQNKz8AuttB4Op0RHi52] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][J6LA4xVLg6RprYZrXKCmX7REBgl3mql6] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][fRSN50DmHzYzrzcosMpSWMYoeoIFSde1] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][6Olg1vz9qE8yJrlzwc5ssjZEJvFcgkz8] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][egaEuJgOIYmNrQNKz8AuttB4Op0RHi52] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][J6LA4xVLg6RprYZrXKCmX7REBgl3mql6] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hMNN46B193fUH4UjNRTpl60RA4Wjurkh] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][7ZLjyaGYE9Du7ggiHmViYxI8ibvM5xPe] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][zjUTCsswiyrSH4Sm6TZqTTxh9lO26K0S] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][EtIh4OYTC4bnNFmtA7HRgUNAU92vsQYV] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! Write PDO after write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][zjUTCsswiyrSH4Sm6TZqTTxh9lO26K0S] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][EtIh4OYTC4bnNFmtA7HRgUNAU92vsQYV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hMNN46B193fUH4UjNRTpl60RA4Wjurkh] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][7ZLjyaGYE9Du7ggiHmViYxI8ibvM5xPe] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][Tve38xz5bAqsTG5rAAAJ02iJyL7uQtFA] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][5zERrepcYbS6JQzjvBQtun0dblpDAGEx] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][iydcXBBtcQ88ma3qF8i7EXdJhRW0E239] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][IStvBVmrdZt733le3J2ldHfoOFaWNKjK] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][iydcXBBtcQ88ma3qF8i7EXdJhRW0E239] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][IStvBVmrdZt733le3J2ldHfoOFaWNKjK] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Tve38xz5bAqsTG5rAAAJ02iJyL7uQtFA] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][5zERrepcYbS6JQzjvBQtun0dblpDAGEx] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][p1sxfVaUzlH1pfJ8zaLAN9M91Vi1Nisp] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qwXoefdykf7O7vF0HuBPsdqr5ZhJOH5F] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][3sUADJ7hSZIv7EVNeXFL7bw55KIoEy8z] Processing: App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][9wX6BOGLYegiVcb3vVjLfkKgziBZ4BtI] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][p1sxfVaUzlH1pfJ8zaLAN9M91Vi1Nisp] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][qivdX2PWkoMWJMjlB965RrZxh3yLpOqu] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! Write PDO after write action"
[2021-06-11 12:20:32][9wX6BOGLYegiVcb3vVjLfkKgziBZ4BtI] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qwXoefdykf7O7vF0HuBPsdqr5ZhJOH5F] Processed:  App\Jobs\WriteExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][3sUADJ7hSZIv7EVNeXFL7bw55KIoEy8z] Processed:  App\Jobs\WriteExpectWrite
[2021-06-11 12:20:32][FiVCsweW4S9lFqFTeSlqACzb5lCCVTkE] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][DcaHEcuSXHjD0Uj9eskhEK7hQR4uwr0i] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qivdX2PWkoMWJMjlB965RrZxh3yLpOqu] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8vzCCtyv0hHzmaanweTLhX9ZTg97Np53] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][OfkKiRns44KebL4ikaWbG399GTTME1m4] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][DcaHEcuSXHjD0Uj9eskhEK7hQR4uwr0i] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][FiVCsweW4S9lFqFTeSlqACzb5lCCVTkE] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8vzCCtyv0hHzmaanweTLhX9ZTg97Np53] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][VCaUYOdYe9aGhM75JuKCMhUBrHacXUNN] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jMyAep6nEK2w9W2FZAPkSv67k5YjVnp8] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OfkKiRns44KebL4ikaWbG399GTTME1m4] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jtjb1icuEa8ffsmjSBl3SMnnPKvufKin] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][SQkjx3kx4nWPWwZLBN0xOnOUNObnIy65] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][VCaUYOdYe9aGhM75JuKCMhUBrHacXUNN] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jMyAep6nEK2w9W2FZAPkSv67k5YjVnp8] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][jtjb1icuEa8ffsmjSBl3SMnnPKvufKin] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][G6kHRgUZgpUgoxeErvewkfOaitcMBaUs] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ctcpQkXDIrols07Ok9mfJwiZASvzj6RR] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][SQkjx3kx4nWPWwZLBN0xOnOUNObnIy65] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][CcBYC0U8dE3AFZ5zKd09daB2CybQjNcM] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][rn4cXzasA3tfZ7mPHNXPxvPDYUYUgdIP] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][G6kHRgUZgpUgoxeErvewkfOaitcMBaUs] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ctcpQkXDIrols07Ok9mfJwiZASvzj6RR] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][CcBYC0U8dE3AFZ5zKd09daB2CybQjNcM] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][vnjYLOpoDylwu0iQexn7oqctSdAYWDwk] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pbe5iuivuMiyawfH3jbK1lZsphwRRqzt] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][rn4cXzasA3tfZ7mPHNXPxvPDYUYUgdIP] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jqyJkVuPY5aAmvR2hmfDxg8GqSoYuBw5] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ciAw7JK20i9ccyaEpDybSGeyytsihEg0] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][vnjYLOpoDylwu0iQexn7oqctSdAYWDwk] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pbe5iuivuMiyawfH3jbK1lZsphwRRqzt] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][jqyJkVuPY5aAmvR2hmfDxg8GqSoYuBw5] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Ws3CNqmI5dZV8vhMhMP3esJU4GhpbCit] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ciAw7JK20i9ccyaEpDybSGeyytsihEg0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YdS1iiB5UWoheznCaVpW0wcHdGnCWN1P] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZIVAGpDka77o0Fjisdqy3T3MxqU0Wdl9] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][0Tnt6RdAqb1p5otCKneVaSr3cq4uBhEe] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Ws3CNqmI5dZV8vhMhMP3esJU4GhpbCit] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][YdS1iiB5UWoheznCaVpW0wcHdGnCWN1P] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][pmOs5YPk3Ve1XJ5hrveTSqoDtIgAK8QN] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZIVAGpDka77o0Fjisdqy3T3MxqU0Wdl9] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][pmOs5YPk3Ve1XJ5hrveTSqoDtIgAK8QN] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][FIAUG0Bm2WllHzxRcYJm1BV5mK7GGHLE] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][FIAUG0Bm2WllHzxRcYJm1BV5mK7GGHLE] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][VSnCvPpsJ7H4HZmiYDbZt9biDjU7uUlK] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][VSnCvPpsJ7H4HZmiYDbZt9biDjU7uUlK] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YxTxRq7ic4N3LZjtrUEtuOabd3Jsu8LU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][YxTxRq7ic4N3LZjtrUEtuOabd3Jsu8LU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xVH9oVcMkiMogF1l1BCrM0kNVcUSbfTJ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][xVH9oVcMkiMogF1l1BCrM0kNVcUSbfTJ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][FH6yTmr5bcc2wJBhXiFFmkq8oUS8IJfx] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][FH6yTmr5bcc2wJBhXiFFmkq8oUS8IJfx] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LHWSNv8GOZjJuI9PXmaWRUdi1cfad0k0] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][LHWSNv8GOZjJuI9PXmaWRUdi1cfad0k0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Rsieotv3xpLIMpUIg7qHCKOGJUcrLYM2] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Rsieotv3xpLIMpUIg7qHCKOGJUcrLYM2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lPhfUaAN4SSiddjyGlnPlx4AgVK9wDCU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][lPhfUaAN4SSiddjyGlnPlx4AgVK9wDCU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][j2YuxXk6w7fkUNm7tQMojF4qOaDa61pZ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][j2YuxXk6w7fkUNm7tQMojF4qOaDa61pZ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][kGAVrt0RwYf8qJedTBz9sKKwgxfMr6fV] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][kGAVrt0RwYf8qJedTBz9sKKwgxfMr6fV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UyqELc4e36eOg8zpAHLS8Qi2NY184XIR] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][csNF2YhWrIYotHRoqowyNhV2V60PJWb0] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][csNF2YhWrIYotHRoqowyNhV2V60PJWb0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][juKerzVqx0YxgmCWOtwtzyikl1xChLwQ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][juKerzVqx0YxgmCWOtwtzyikl1xChLwQ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][zexak8yr7cqZpdFgHvUUYQsJvNtWqNKp] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][zexak8yr7cqZpdFgHvUUYQsJvNtWqNKp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LtTG4P54XrYauy5XiBohCQ7ZYLTr8NNA] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][LtTG4P54XrYauy5XiBohCQ7ZYLTr8NNA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][C1SSP6XWGWGYz96nNjYM6pITzsqRKoGK] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][C1SSP6XWGWGYz96nNjYM6pITzsqRKoGK] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][waqlwWDHyReiEbYVx7l9X2Z12WqAAIGq] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][waqlwWDHyReiEbYVx7l9X2Z12WqAAIGq] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][nEHStf6FwzNrpiCoGLMBPkkeCS7ehqTj] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][nEHStf6FwzNrpiCoGLMBPkkeCS7ehqTj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jkHmcGviqCd5sFcWKbRkjxzLiPKuaGPf] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][jkHmcGviqCd5sFcWKbRkjxzLiPKuaGPf] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][mTmrMsgRsJ6GHn9KSSQuGVQCZnsCF2ox] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][mTmrMsgRsJ6GHn9KSSQuGVQCZnsCF2ox] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2AhLZAkNIXBZMKIgm9Xz9lWHwemNAOnC] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][2AhLZAkNIXBZMKIgm9Xz9lWHwemNAOnC] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][EE8wAG9hPmHrxR0UIeo8vi3Dk3eFi0qa] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][EE8wAG9hPmHrxR0UIeo8vi3Dk3eFi0qa] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][0Tnt6RdAqb1p5otCKneVaSr3cq4uBhEe] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lwhsXp8ft6yqIKEakJhv1ETzmYDwNDze] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][lwhsXp8ft6yqIKEakJhv1ETzmYDwNDze] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][XXNH1WiwWo4nKXkuGv7i1WXMVRlTIEp8] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][XXNH1WiwWo4nKXkuGv7i1WXMVRlTIEp8] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2UmGdYfpOLbdWAQXQSbtQpIWTyEtMqwq] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][2UmGdYfpOLbdWAQXQSbtQpIWTyEtMqwq] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][W7Eu42QUMhYOPBm27SfrajJvL0gbOTtd] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][W7Eu42QUMhYOPBm27SfrajJvL0gbOTtd] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][SUAMZFcfwnbQ1FaI5GLS0lKQ5Oq4idDA] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][SUAMZFcfwnbQ1FaI5GLS0lKQ5Oq4idDA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][w9xT5uBnTrbhvYmcwvctCz5b9NYeBcnj] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][w9xT5uBnTrbhvYmcwvctCz5b9NYeBcnj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][f1eu0Q3cPJoknlsbyK1RiYyCMQKfL583] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][f1eu0Q3cPJoknlsbyK1RiYyCMQKfL583] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][mQTE3yN73fNtKHVJuire6LWYd5fdXF3J] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][mQTE3yN73fNtKHVJuire6LWYd5fdXF3J] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][bfZbdlc7ZrxjN4NnCdALM8PfzTyl8bIQ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][bfZbdlc7ZrxjN4NnCdALM8PfzTyl8bIQ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HExVNYEZXpbXugZMeCUaA8HsOgPOSpkW] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][HExVNYEZXpbXugZMeCUaA8HsOgPOSpkW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][9WBWo9PCoo0DpH0HdnzD1JCLL0j1q63a] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][9WBWo9PCoo0DpH0HdnzD1JCLL0j1q63a] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ofbevtawUd07M4jf2Q8JV2mzgFNwMhJo] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hTFQNQ2MUke1wKxP9huGbRvtskx48ido] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][hTFQNQ2MUke1wKxP9huGbRvtskx48ido] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][JgayrYA1wiWSUWD101FHRhdDomufZ70E] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][JgayrYA1wiWSUWD101FHRhdDomufZ70E] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][JN0o2lskkbv1JMjaOsVXT5YfGM0CtzhW] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][JN0o2lskkbv1JMjaOsVXT5YfGM0CtzhW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QRucfgxEcBXe3RsxrSxvdl7nVSZZLZAW] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][QRucfgxEcBXe3RsxrSxvdl7nVSZZLZAW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LK6Av24epm4HycWgYV8qGA2Cx5Wj2ayW] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][LK6Av24epm4HycWgYV8qGA2Cx5Wj2ayW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jxVN3MDrNPSS2nhEdid082dQ1iqsGks3] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][jxVN3MDrNPSS2nhEdid082dQ1iqsGks3] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ObizSKs7prz8H77XiATlJE5dFLDUTPnF] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ObizSKs7prz8H77XiATlJE5dFLDUTPnF] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][h8OtrEpzrjelDh5i62X0JgCsNJC3Prg5] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][h8OtrEpzrjelDh5i62X0JgCsNJC3Prg5] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZrjsVFTMkW2V8g1XJxfUSfqqxhqknQyV] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ZrjsVFTMkW2V8g1XJxfUSfqqxhqknQyV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][NhCNU7gqIXOCVfUmfbV4SRfndfdHIlYY] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][NhCNU7gqIXOCVfUmfbV4SRfndfdHIlYY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pU7kBLy5mblLGY0fhixoX6Bv5vhcHWqN] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][pU7kBLy5mblLGY0fhixoX6Bv5vhcHWqN] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][1TEME0bQg6Chxe60PLWTJKk26qKuUKIY] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UyqELc4e36eOg8zpAHLS8Qi2NY184XIR] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UQ97WSxpT2NQQq2kQ24uIK4nSzYi9zcK] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ofbevtawUd07M4jf2Q8JV2mzgFNwMhJo] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jVsU6b5sUVsaVET4IsUKHMKaBUjE6GuG] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][1TEME0bQg6Chxe60PLWTJKk26qKuUKIY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][gkwcaGtxSicAKLEuYpMkDkK96dOzcBYy] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UQ97WSxpT2NQQq2kQ24uIK4nSzYi9zcK] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][fdtv7KcFVZAQpv0bECJCeG243bnQRlIv] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Fx7zIuX8RJJIfRewsPqRP00YODWjhCv0] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][jVsU6b5sUVsaVET4IsUKHMKaBUjE6GuG] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][gkwcaGtxSicAKLEuYpMkDkK96dOzcBYy] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][PrrXJGQuRds6DVOUY7tEuWj8HurPYecN] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fdtv7KcFVZAQpv0bECJCeG243bnQRlIv] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Fx7zIuX8RJJIfRewsPqRP00YODWjhCv0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][nBPWhVTvNX0rgAvIqOBxHnDaaEH0kqFl] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][hVmbLXHknlaribtFzHcZxQsIDZMaaEjl] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qL65KKTPnZejCyO2N88l2dciJuhBcVPZ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][PrrXJGQuRds6DVOUY7tEuWj8HurPYecN] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][nBPWhVTvNX0rgAvIqOBxHnDaaEH0kqFl] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][BDTAjYBogkUKOYGvIeoAcjckJmtLBUKN] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hVmbLXHknlaribtFzHcZxQsIDZMaaEjl] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qL65KKTPnZejCyO2N88l2dciJuhBcVPZ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][1ln88umc6JLflmqifbNQIbPdnYhei4Ot] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][UoauY2wWkhSZlHDpU9y9QoTF3iw3Eok2] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7fFKuq0UoQAq7gjAto41fbLbJzS02tV1] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][BDTAjYBogkUKOYGvIeoAcjckJmtLBUKN] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][suRMQ0h3v3VYxuT3hwLL3jkDx3TzV6kL] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][1ln88umc6JLflmqifbNQIbPdnYhei4Ot] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7fFKuq0UoQAq7gjAto41fbLbJzS02tV1] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UoauY2wWkhSZlHDpU9y9QoTF3iw3Eok2] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][gw5SnxBpdfIaridrBodk5AIgtS2U5jgB] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][a3FdY0timx5Zm1obU240fBcdRrn4J3dI] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][kSlnAELx6APQ04qn4xQNzpWsQ1ARFEyd] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][suRMQ0h3v3VYxuT3hwLL3jkDx3TzV6kL] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][dS4Dr595Ob8JZpTh5L4AqLP8mm2Cbx6L] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][gw5SnxBpdfIaridrBodk5AIgtS2U5jgB] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][a3FdY0timx5Zm1obU240fBcdRrn4J3dI] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][kSlnAELx6APQ04qn4xQNzpWsQ1ARFEyd] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][LBDVGihY2TQSfozZuBJetvUV0nxobuCD] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][RVddSDnroKdMe3yyX1sn9SpTLWnK78jY] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][MfemMzv6sUOi8gTmlJ9kQLcYneDwQIQV] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][dS4Dr595Ob8JZpTh5L4AqLP8mm2Cbx6L] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][HjPDyULzvnzvWipxxsg3tAjwLu5TrrKh] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LBDVGihY2TQSfozZuBJetvUV0nxobuCD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][RVddSDnroKdMe3yyX1sn9SpTLWnK78jY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][MfemMzv6sUOi8gTmlJ9kQLcYneDwQIQV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xUiC2UsLWx5CkBbdb6f5YQAOHXPGxC9m] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][YO1AUP6mPCHljZcqUdwmr6rNE6wcBvIz] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][gn1UUzg8mArSVD80AQeLhIwHXCfQ1NQZ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HjPDyULzvnzvWipxxsg3tAjwLu5TrrKh] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][3Co5vZfvtidBM5oEq5Rx3Ffho0PwYvFc] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xUiC2UsLWx5CkBbdb6f5YQAOHXPGxC9m] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YO1AUP6mPCHljZcqUdwmr6rNE6wcBvIz] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][gn1UUzg8mArSVD80AQeLhIwHXCfQ1NQZ] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][6RTjtWhuesttDH4ShifroXChCOWZZ2SU] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Vpe9Ur70dqfA7BSYaDdQuTADth7nVN6i] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][X75Mei9ZLBmQtLeDe6I8XIkRUefZTHHh] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3Co5vZfvtidBM5oEq5Rx3Ffho0PwYvFc] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ujCGGX04YXsjXZKYgc7Sqg7tyie0INiU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][6RTjtWhuesttDH4ShifroXChCOWZZ2SU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Vpe9Ur70dqfA7BSYaDdQuTADth7nVN6i] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][X75Mei9ZLBmQtLeDe6I8XIkRUefZTHHh] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][B6xrEyC0YDojDKS2k2uMmOEwewqkIMH4] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][B0vixbHcLuQUCs3UZkj1h62XGKkwroFD] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ujCGGX04YXsjXZKYgc7Sqg7tyie0INiU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][sGnQ49k7WO0dIBkkYWhu03Fn5mdLnXST] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][wsVP7jOJ32PA7rtRvEFqT4dVZyrrkoJ5] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][B6xrEyC0YDojDKS2k2uMmOEwewqkIMH4] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][B0vixbHcLuQUCs3UZkj1h62XGKkwroFD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][sGnQ49k7WO0dIBkkYWhu03Fn5mdLnXST] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][6TwVD1o6JKx4mYbcaJ3IdhDjzrBSBEuI] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wsVP7jOJ32PA7rtRvEFqT4dVZyrrkoJ5] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][bI2aa8CKETLzEzhAAEg3GKF6uohv9jWz] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][XdnayZ23wLHJMKc0V7jqj540rPJb6wGY] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][SYansd3i6Td9h1v03iZfJRmx6ZLsYCxI] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][6TwVD1o6JKx4mYbcaJ3IdhDjzrBSBEuI] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][XdnayZ23wLHJMKc0V7jqj540rPJb6wGY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][bI2aa8CKETLzEzhAAEg3GKF6uohv9jWz] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][w6U1vTm1JEU9oH1Vu4AZ8Y0bm3XDv0UZ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][mwLmN9Zvxfe2zA3HlJJQ7oqSGzlNTH3I] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4I1nzM8qDT0OijoI7H0GLcb9X1v4fjiP] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][SYansd3i6Td9h1v03iZfJRmx6ZLsYCxI] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][s8FMmoSEDUzZ4kWkp9E94p8xuXLprVsj] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][w6U1vTm1JEU9oH1Vu4AZ8Y0bm3XDv0UZ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][mwLmN9Zvxfe2zA3HlJJQ7oqSGzlNTH3I] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4I1nzM8qDT0OijoI7H0GLcb9X1v4fjiP] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][b53Zte1v7COcPPSGBoFBU75U0JzvYaBg] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QiNyPG9pWCLzDwPhMbnlJy5IHQph5ye0] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][s8FMmoSEDUzZ4kWkp9E94p8xuXLprVsj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][GN3iqc8QoNtneDqVguhn6g3dxOllOjLg] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][9S4bBee2woRPO3yEEzssolO7qG3ZGXnp] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][QiNyPG9pWCLzDwPhMbnlJy5IHQph5ye0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][b53Zte1v7COcPPSGBoFBU75U0JzvYaBg] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][9S4bBee2woRPO3yEEzssolO7qG3ZGXnp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][GN3iqc8QoNtneDqVguhn6g3dxOllOjLg] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][5wqR8pNU63TCGMntfI5kkuSaYz8ZsUKr] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][IxkXgQYbg8UZwHWjZeOCbadduZLJiHJB] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][rx636Y25IOJEGoLXmgV8IDAyqy2tKo8v] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][PC2PI0F4ODN5E7Jt64ViNBBjQyJzhNid] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][5wqR8pNU63TCGMntfI5kkuSaYz8ZsUKr] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][IxkXgQYbg8UZwHWjZeOCbadduZLJiHJB] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][rx636Y25IOJEGoLXmgV8IDAyqy2tKo8v] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][oFyneShyP9oI0EjEEdGrO29Xkx9TxbOe] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][PC2PI0F4ODN5E7Jt64ViNBBjQyJzhNid] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][rIvQaf2QqbGn3D6Sf851u7mMcztoWg24] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fXEPFSn0iupZ2zLNaHnp5KeU6AT5w40O] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][JFy80mHxZpUk1odKxKgup5J7Phoeg3yE] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][oFyneShyP9oI0EjEEdGrO29Xkx9TxbOe] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][rIvQaf2QqbGn3D6Sf851u7mMcztoWg24] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fXEPFSn0iupZ2zLNaHnp5KeU6AT5w40O] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][JFy80mHxZpUk1odKxKgup5J7Phoeg3yE] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ivM8xYKnZDnF0J5fI1bRpuuSIAbkIFbz] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][dXSkHPpWPZ9rwE0SsQL4i2oM8KJtIjr2] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QMLWIi4BNfxpFuKhPcOteyoHLZASLMQv] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HzrXjRjebsPYV2Sx3mpCoccSqs1FHbZP] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ivM8xYKnZDnF0J5fI1bRpuuSIAbkIFbz] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QMLWIi4BNfxpFuKhPcOteyoHLZASLMQv] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][dXSkHPpWPZ9rwE0SsQL4i2oM8KJtIjr2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HzrXjRjebsPYV2Sx3mpCoccSqs1FHbZP] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KvsLI9f0Iu5OF0xbFpjq1ttcBdn9gg1L] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Z4Gbr9tnGn6Why4EWvpAnTlXTBabbsRh] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][f5GdpNXEfQXbpuLlViVdASyLOI6LKmt8] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pLaTnDHZGkj3f486acdk4uwLG8B986DY] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Z4Gbr9tnGn6Why4EWvpAnTlXTBabbsRh] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KvsLI9f0Iu5OF0xbFpjq1ttcBdn9gg1L] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pLaTnDHZGkj3f486acdk4uwLG8B986DY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][f5GdpNXEfQXbpuLlViVdASyLOI6LKmt8] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][GPDey1o3QfNjBrMNOO4VVIpS4lAe0SQ9] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][q027ddsJxHwuzX677Ve7x1xl0vInqMWZ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][brxz7vOvYkE2lXFxQXn2wcvq03QaScAl] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xN5n72RzNFG9utTJNEbiny1AwfOuX42L] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][GPDey1o3QfNjBrMNOO4VVIpS4lAe0SQ9] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][q027ddsJxHwuzX677Ve7x1xl0vInqMWZ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][brxz7vOvYkE2lXFxQXn2wcvq03QaScAl] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xN5n72RzNFG9utTJNEbiny1AwfOuX42L] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HQkPkk0BF7JFn3s0wjqbh91UEhFTECIA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ux1iE2d3g3vZ3wTpCSxYC68fDUr10Fhi] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][El5zxWDrvh8GrhLtVTX81FMNGbkqtlC3] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][EXjtXy90livnOfCCTNRhodEcJsDopJSe] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][HQkPkk0BF7JFn3s0wjqbh91UEhFTECIA] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ux1iE2d3g3vZ3wTpCSxYC68fDUr10Fhi] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][El5zxWDrvh8GrhLtVTX81FMNGbkqtlC3] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2geWYen57fmszRYJVwKdxCcuMD25Y9SV] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][EXjtXy90livnOfCCTNRhodEcJsDopJSe] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][67z0hW5oFgMbkGs3BffYQGqmtWLTxz2K] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2OXxXB5ErRmwpyw5ozNy7S2VkKjlL1is] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HBUn8DYFAYnhZdROH1BEPMNgp7CDMwfK] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][2geWYen57fmszRYJVwKdxCcuMD25Y9SV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][67z0hW5oFgMbkGs3BffYQGqmtWLTxz2K] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2OXxXB5ErRmwpyw5ozNy7S2VkKjlL1is] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HBUn8DYFAYnhZdROH1BEPMNgp7CDMwfK] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fJ3m7k9TUnbTJNffBHj3CgTpHVjL4BVW] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QjjvAuVDo0ybF4fXe3wqCIG7uoQ6PBxv] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HMIbAKcxswylWoV4Y1uhv4yMZJHEkkuQ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qlT4WENhJUEWJO273S8wGXJWcDS4YDp0] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][fJ3m7k9TUnbTJNffBHj3CgTpHVjL4BVW] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][QjjvAuVDo0ybF4fXe3wqCIG7uoQ6PBxv] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qlT4WENhJUEWJO273S8wGXJWcDS4YDp0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HMIbAKcxswylWoV4Y1uhv4yMZJHEkkuQ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pZuqlok0wavZV746glBLo6V4idPFEReA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][iDIKg8WI0bcReG7EIz73EwMC5kzRVjv7] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fMJLD9EU0ggJIi3Qm8SJXXZwgA4xtQa4] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][W55CCnoqmUVxv33RM3cPNFhsIMDt9Luo] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][pZuqlok0wavZV746glBLo6V4idPFEReA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][iDIKg8WI0bcReG7EIz73EwMC5kzRVjv7] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fMJLD9EU0ggJIi3Qm8SJXXZwgA4xtQa4] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][W55CCnoqmUVxv33RM3cPNFhsIMDt9Luo] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][a0vwTYJTBhhJY2r94dyEGbSMyCX0HmJS] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][v8uvGMYN5L0ZPLZvKni1CrSsNdJYBETn] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fUnhtEoMndnw3Hq3giBjKKrSFfRgVDfp] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][E5TxBJ0KEs1yTMYLkNwAzLEymEnoJm41] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][a0vwTYJTBhhJY2r94dyEGbSMyCX0HmJS] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][v8uvGMYN5L0ZPLZvKni1CrSsNdJYBETn] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fUnhtEoMndnw3Hq3giBjKKrSFfRgVDfp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][E5TxBJ0KEs1yTMYLkNwAzLEymEnoJm41] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][nrrTQDQ540uapGUqQMSVsmyJ7bkxH1vg] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][oEkRLluXcO5fow3xspQamUb1dkrmlmRl] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][W9VOw7G3eJ1N1t4mGt7ZjwBXn5A8TFea] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Aqv11O17hz9D7QG7as2qxuAJctufJKUT] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][nrrTQDQ540uapGUqQMSVsmyJ7bkxH1vg] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][W9VOw7G3eJ1N1t4mGt7ZjwBXn5A8TFea] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Aqv11O17hz9D7QG7as2qxuAJctufJKUT] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][oEkRLluXcO5fow3xspQamUb1dkrmlmRl] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Bfdb9B6Nv3H5RFLyjT4otjOsk6vuvrh7] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2uwcKJI8Lxq3KJgJ3WtHLNOe6P4skWFp] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xbTeEmCmOHOcJvEyKqMTzbLwAGdvWZ8j] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][1y69OBA5HDe8Ciix0wUIUQ8ZqCPLXLp4] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Bfdb9B6Nv3H5RFLyjT4otjOsk6vuvrh7] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2uwcKJI8Lxq3KJgJ3WtHLNOe6P4skWFp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xbTeEmCmOHOcJvEyKqMTzbLwAGdvWZ8j] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][1y69OBA5HDe8Ciix0wUIUQ8ZqCPLXLp4] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ETipY5koHU54VC3i9decwccYrzaJz2jq] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][RZA5yDXZwJOZdDjgImCZ0Nz3OY48GXjD] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lpzd9IGPxTpQBdw1jvynKoZt8MFzofN0] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LIYqqpmec85bADu4XnMKcmX6dbGkWccg] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ETipY5koHU54VC3i9decwccYrzaJz2jq] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LIYqqpmec85bADu4XnMKcmX6dbGkWccg] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][RZA5yDXZwJOZdDjgImCZ0Nz3OY48GXjD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lpzd9IGPxTpQBdw1jvynKoZt8MFzofN0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OmrBV5XKi4d94LsLDWhmusTKFhEkwyJJ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Co3fxxGP3uBxZZwkwKLd5YOjRZTWyaQq] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][WGkQEIjx6HOL5187YxP6QwyEuI6OY0ZG] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pEiWjSE0pOg06naU6fA5S83mzYXUZGMc] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OmrBV5XKi4d94LsLDWhmusTKFhEkwyJJ] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Co3fxxGP3uBxZZwkwKLd5YOjRZTWyaQq] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][WGkQEIjx6HOL5187YxP6QwyEuI6OY0ZG] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pEiWjSE0pOg06naU6fA5S83mzYXUZGMc] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][oOAAjWWPGVc5uyU76W4sce4Rm1g7WvJ1] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Y2qr67oCi6gIpq1TJaKmmjYyDlb8Cu5l] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QEhBJsThBxgVdTF1yD8mklB6AoXrwwC6] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Rb60QeqxyaiWUXAYFcHneXyqgmq61VID] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][oOAAjWWPGVc5uyU76W4sce4Rm1g7WvJ1] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Y2qr67oCi6gIpq1TJaKmmjYyDlb8Cu5l] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Rb60QeqxyaiWUXAYFcHneXyqgmq61VID] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QEhBJsThBxgVdTF1yD8mklB6AoXrwwC6] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3JiHfQNVlnXlAPD8A2K1GDIp5DqXok4C] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][WwDrJVDz4tKtnARTXAsOXjKTXGJIXZYa] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][XFoSgQw10Fysm1MBqduJXWiFNu3K5Wek] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lfEFfMxz63axOkHKJcIHsLaJZKbGUz26] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][3JiHfQNVlnXlAPD8A2K1GDIp5DqXok4C] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][WwDrJVDz4tKtnARTXAsOXjKTXGJIXZYa] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][XFoSgQw10Fysm1MBqduJXWiFNu3K5Wek] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][JOEVS26UE5Y1q2HMuf981tBbMCaQJ4IN] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lfEFfMxz63axOkHKJcIHsLaJZKbGUz26] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][19CiXD3hwKuwn66izaMcmyvQtECLHnGm] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][boKp6FaHTtYy0API989JvyfkRZh5xTQr] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][XaGJgA4L2m1PxlVo59Yf0WsfG3Wd1zpo] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][JOEVS26UE5Y1q2HMuf981tBbMCaQJ4IN] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][19CiXD3hwKuwn66izaMcmyvQtECLHnGm] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][boKp6FaHTtYy0API989JvyfkRZh5xTQr] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][XaGJgA4L2m1PxlVo59Yf0WsfG3Wd1zpo] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Ri1xWq856gXnTi1TMB7lxok5dxV5Y6P3] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Q6VGMGCWbS88Bf7HviZ7NSFEGgXOgONT] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][JIpoNjWEcp6ubD3yK8dgFgdai7aAr4d9] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][TGACxkWq9X6flNigf4acMuG4hbD9vgov] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Ri1xWq856gXnTi1TMB7lxok5dxV5Y6P3] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Q6VGMGCWbS88Bf7HviZ7NSFEGgXOgONT] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][JIpoNjWEcp6ubD3yK8dgFgdai7aAr4d9] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][CtdRt4StSzASeHhM1NNDkadCNFhPMlJJ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][CtdRt4StSzASeHhM1NNDkadCNFhPMlJJ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Bpudt4vfzDFJZnjZkWAJo6pY9UgseDPV] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Bpudt4vfzDFJZnjZkWAJo6pY9UgseDPV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hNpPdKM0lFoavybzYwTPx9ASDsHhN8ld] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][hNpPdKM0lFoavybzYwTPx9ASDsHhN8ld] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][AIo7X6Ca1MRHI6Ifmd6evYsvsi2l352D] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][AIo7X6Ca1MRHI6Ifmd6evYsvsi2l352D] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][IC4np2djLQl4fIsM3iOwoyHriFbwxNHb] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][IC4np2djLQl4fIsM3iOwoyHriFbwxNHb] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][n8qfQJQ335aJt6D96lYm93BUDI479xsR] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][n8qfQJQ335aJt6D96lYm93BUDI479xsR] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][FPpYsjzchnOYEqK8Au9UrOTEeiURAc0a] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][FPpYsjzchnOYEqK8Au9UrOTEeiURAc0a] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][N9EfZbsN4pyLV9lLIsZL6lkxh9dMnwkW] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][N9EfZbsN4pyLV9lLIsZL6lkxh9dMnwkW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7T60WfOUTe7EVVgKdO6cGH4MBHReNrF9] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][TGACxkWq9X6flNigf4acMuG4hbD9vgov] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][mBSKJz68AF8TgpLIaKJQTfX1xlokWoMh] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][mBSKJz68AF8TgpLIaKJQTfX1xlokWoMh] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][aBbHl1MlIzaaesBC5Zk6i6oVeU8DE2xO] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][aBbHl1MlIzaaesBC5Zk6i6oVeU8DE2xO] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HXJB5SFYoU6PkjBoWft04ZX9UsQyoKR6] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][HXJB5SFYoU6PkjBoWft04ZX9UsQyoKR6] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][PJEX2M2g3qhj2vrjJMuhFCXuMnT8xZce] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][PJEX2M2g3qhj2vrjJMuhFCXuMnT8xZce] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YMCoGANGeeNhA95aggPSKYvTGZhBoV4x] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][YMCoGANGeeNhA95aggPSKYvTGZhBoV4x] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Q4RRpsJRH8Tg6GLiZJ2OSJo6QUiphdsx] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Q4RRpsJRH8Tg6GLiZJ2OSJo6QUiphdsx] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3vsioON65f1O4VIrIBwUyvwwXfnuNwzv] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][3vsioON65f1O4VIrIBwUyvwwXfnuNwzv] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][0usaKdg12I0uQY6XsXe1xUcJtusCIXXv] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][0usaKdg12I0uQY6XsXe1xUcJtusCIXXv] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][0kBrybToMEql10yVkgqqTaX2Vs6oMWkG] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YnECogSvQGiNoXhCG5NcG8wU6gswMeQB] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][YnECogSvQGiNoXhCG5NcG8wU6gswMeQB] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wLWnpHLR32m07tOYBqWgcmfY9QIh5Xiq] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][wLWnpHLR32m07tOYBqWgcmfY9QIh5Xiq] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qv0wsLoxM9ftujjrifqmsQdGuq9uzcr5] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][qv0wsLoxM9ftujjrifqmsQdGuq9uzcr5] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8Z930kNlykj6aSFDZCsnaE7FmOJSkKtx] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8Z930kNlykj6aSFDZCsnaE7FmOJSkKtx] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hPENi2RdlPoZyiTXRYGkGVMu6Y30EFZm] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][hPENi2RdlPoZyiTXRYGkGVMu6Y30EFZm] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][STcOyGUB8LfeW2CvrJ9bGdZKkY3qZH0e] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][STcOyGUB8LfeW2CvrJ9bGdZKkY3qZH0e] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Hv3N79u13jlpKoYpY9Xy9kk52xd3OZki] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Hv3N79u13jlpKoYpY9Xy9kk52xd3OZki] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][uRuORnlii0DGiND5AhdFEohw6eV5NLlm] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][uRuORnlii0DGiND5AhdFEohw6eV5NLlm] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][N1dmwjI46IdCy7b4ksyd0pdJlRVyoEO1] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][tEhChE9YLQZbvBy0lInC6Q0OxEE69XWQ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][tEhChE9YLQZbvBy0lInC6Q0OxEE69XWQ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Do49wUGXvxUiawzcVMgtDVF7r6ewlWbL] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Do49wUGXvxUiawzcVMgtDVF7r6ewlWbL] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZSyaZv3j14fewRFoU1N10YUOWnCBG6Mk] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ZSyaZv3j14fewRFoU1N10YUOWnCBG6Mk] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Rr0KgGcUef9T7EqVseNfwBdELxooP7CN] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Rr0KgGcUef9T7EqVseNfwBdELxooP7CN] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][oTo5nd08O9KGv42SHsYu6zIpRQpTAxqW] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][oTo5nd08O9KGv42SHsYu6zIpRQpTAxqW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OQrORlqW1dGq2oCDP0D4hdgk8GBwO2Xj] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][OQrORlqW1dGq2oCDP0D4hdgk8GBwO2Xj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][J6GhtZqumkFM5ckKPrSapJEAc439TK9h] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][J6GhtZqumkFM5ckKPrSapJEAc439TK9h] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][psvQBDMYySd6JD2LvLwKSNjMFnpVCD1j] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][psvQBDMYySd6JD2LvLwKSNjMFnpVCD1j] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3qBlAUR6zx4U9VZSBDVdSgnXjJONZ1ag] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][7T60WfOUTe7EVVgKdO6cGH4MBHReNrF9] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3qBlAUR6zx4U9VZSBDVdSgnXjJONZ1ag] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][N1dmwjI46IdCy7b4ksyd0pdJlRVyoEO1] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][0kBrybToMEql10yVkgqqTaX2Vs6oMWkG] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][SatCQ0yq4lgS7F86TnxKm5VXh5nrkDm2] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][9kh7YGktysI3REmnBhvWcexRv7859uat] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][f3nYoI082H9IWIrOnGdcDj7nHSVkD4Qb] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][5RKIloGSsPhkv4k3qFRl1QXrP0mxsHEX] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][SatCQ0yq4lgS7F86TnxKm5VXh5nrkDm2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][9kh7YGktysI3REmnBhvWcexRv7859uat] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][f3nYoI082H9IWIrOnGdcDj7nHSVkD4Qb] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][5RKIloGSsPhkv4k3qFRl1QXrP0mxsHEX] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UdhfTLQUJVsNReqyCecCu1SgD9JAq1q2] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][aVzLdvDKanhrGXg4ndJcBsN8DOTnnnn9] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ziF1nYo1eXGY3MNdgt5eCVuBQ1smbtVA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2YUGsaC5KVHYs610HsleRBA8zxtHrtrV] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][UdhfTLQUJVsNReqyCecCu1SgD9JAq1q2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ziF1nYo1eXGY3MNdgt5eCVuBQ1smbtVA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][aVzLdvDKanhrGXg4ndJcBsN8DOTnnnn9] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2YUGsaC5KVHYs610HsleRBA8zxtHrtrV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7XmCl9N1F7ljs5oMQZsFUb2mB8gU2vT0] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xqPqdprNauYPHK3HNDHE5Sbfwkd9QaV2] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fotcFwQTGjm313euv2f3w2Uhsv9ngHXI] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8Y188e7nOxulvmNJXEFDutpDBV6jq7E2] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][7XmCl9N1F7ljs5oMQZsFUb2mB8gU2vT0] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][fotcFwQTGjm313euv2f3w2Uhsv9ngHXI] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xqPqdprNauYPHK3HNDHE5Sbfwkd9QaV2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8Y188e7nOxulvmNJXEFDutpDBV6jq7E2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][WTb7YKAXe66BAUxD5cVB4EXHMcMApcUm] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][gD7OZVezFB52QvzyyzGwz0qW8cez2kZn] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YOu80rizOYUtpFnEYiBfxpvcZWk7eUOj] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][T03KnNjlmyut9oEFbwKJofKb9WtULVfK] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][WTb7YKAXe66BAUxD5cVB4EXHMcMApcUm] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][YOu80rizOYUtpFnEYiBfxpvcZWk7eUOj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][gD7OZVezFB52QvzyyzGwz0qW8cez2kZn] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][iBsjbRXYYbFJfE16UYDpXuOSG0hR1gl4] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][T03KnNjlmyut9oEFbwKJofKb9WtULVfK] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pO0FMjo6qFlm6MnjU6MqNhymBdtS7bo3] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][kFiXwP3Ljo7POPCpP3ft1bRc2rRozjEl] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][tRxddkXw0qcinKOKqm2wqOCR8fkONkNC] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][iBsjbRXYYbFJfE16UYDpXuOSG0hR1gl4] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][LLbVGwJsdwyu49JTpacf7PfsYxPVrTcW] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pO0FMjo6qFlm6MnjU6MqNhymBdtS7bo3] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][kFiXwP3Ljo7POPCpP3ft1bRc2rRozjEl] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][tRxddkXw0qcinKOKqm2wqOCR8fkONkNC] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][w33pvsRl0ztAyXcXBB5E1i5K5lnIvHgS] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][m5WcBzWFyVhGAhti5Bxxt0t4sBr4ququ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][172oOqunHqIq1lWkCSK8ey6YNKhQHChF] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LLbVGwJsdwyu49JTpacf7PfsYxPVrTcW] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][w33pvsRl0ztAyXcXBB5E1i5K5lnIvHgS] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZYGzFLcGP9jUe4otWdXK1MDC0B4v2UaM] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][m5WcBzWFyVhGAhti5Bxxt0t4sBr4ququ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][172oOqunHqIq1lWkCSK8ey6YNKhQHChF] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][0Y4AMusU5Q678rzGDoRXZfUpcAvfvbgK] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][eM1NAK13sL5niD1fkskKuWncxRvo2nSI] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][BTRVIaeEGcotWHGcOkd8eMWtexNHHilk] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ZYGzFLcGP9jUe4otWdXK1MDC0B4v2UaM] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][0Y4AMusU5Q678rzGDoRXZfUpcAvfvbgK] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][5tMeOnXlHpyRYX1KK9X8UtnYSYpigYvW] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][eM1NAK13sL5niD1fkskKuWncxRvo2nSI] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][BTRVIaeEGcotWHGcOkd8eMWtexNHHilk] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Hw4pKHEBHCDkGuBM3Ur4yJs8z8m0DiN0] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][qukhgSbJvEQt9nnlD9hAWkW5E33sWQdl] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wFyeuKw90lEnMoXRPagVtYuFaMokIIp1] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][5tMeOnXlHpyRYX1KK9X8UtnYSYpigYvW] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Hw4pKHEBHCDkGuBM3Ur4yJs8z8m0DiN0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wFyeuKw90lEnMoXRPagVtYuFaMokIIp1] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][uSXxWnvTzFLLNP92KsvxbXdh0StGRYKn] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qukhgSbJvEQt9nnlD9hAWkW5E33sWQdl] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][B4vdLURyObq5Qvkgih9d9l4T3XquwEQw] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][g6zViG7Ty5uBHm5HUFGSJxakTJXpYkpJ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8ivV3dcaazzDW6qLt00RrHKrgeFF8fdP] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][uSXxWnvTzFLLNP92KsvxbXdh0StGRYKn] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][B4vdLURyObq5Qvkgih9d9l4T3XquwEQw] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][g6zViG7Ty5uBHm5HUFGSJxakTJXpYkpJ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][VXvXvPrjeHTq5OITehlaomwzKiO17bU4] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8ivV3dcaazzDW6qLt00RrHKrgeFF8fdP] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][GoHOEmYYIc6lz9CXkrUfWFDVC8FLpCtV] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3auZ08cmHPPCtkuT0JSTy1XRacG2mE8b] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ttgjIQbUCSpvsynZCo0JuC5GqiZzT9tt] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][VXvXvPrjeHTq5OITehlaomwzKiO17bU4] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][GoHOEmYYIc6lz9CXkrUfWFDVC8FLpCtV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3auZ08cmHPPCtkuT0JSTy1XRacG2mE8b] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][yJ7I2YBRi3vnYlYJgxEWUSA5yO2Eb6sy] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ttgjIQbUCSpvsynZCo0JuC5GqiZzT9tt] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fud172tHqrCaOy6OqhDqa7N1WEk8xbeU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][eDcrqVAeoSHUC1LP6vqsW0jH9Us8g1nS] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QUK48OowkzHffKbQAyopZANVCJgGd99F] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][yJ7I2YBRi3vnYlYJgxEWUSA5yO2Eb6sy] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][fud172tHqrCaOy6OqhDqa7N1WEk8xbeU] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][AGEAd9qhD0qBNgn1qrbBHNZPpzmeJq4A] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][eDcrqVAeoSHUC1LP6vqsW0jH9Us8g1nS] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QUK48OowkzHffKbQAyopZANVCJgGd99F] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][iFUfqSKJN87T5el0AHuRsZk3VFcYCFOM] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][N37JcbuCjuKqqHm5e37jyb7I0oN8P4dX] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][DvukM4mtTXdbwnfFUMgkyCHTmXzgJD30] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][AGEAd9qhD0qBNgn1qrbBHNZPpzmeJq4A] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][iFUfqSKJN87T5el0AHuRsZk3VFcYCFOM] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ApOlyE4mv94JQ4ADCfOxXeMSniWs0PvX] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][DvukM4mtTXdbwnfFUMgkyCHTmXzgJD30] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][N37JcbuCjuKqqHm5e37jyb7I0oN8P4dX] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][MIkZv1HcTh24lHN5AVx2ODRIR9RHoh70] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][UephThNePF4TbsGZTs049vNZvZRGKppn] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][W70DcprMS79FTon3ylBKqge6gnPpG4jH] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ApOlyE4mv94JQ4ADCfOxXeMSniWs0PvX] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][MIkZv1HcTh24lHN5AVx2ODRIR9RHoh70] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][66Af2OoxaEus6SENc3NA9jK93d9TKbgR] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][WdY3PjbDE2BadQwaj3zUChF4KE2IXjbU] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UephThNePF4TbsGZTs049vNZvZRGKppn] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][W70DcprMS79FTon3ylBKqge6gnPpG4jH] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][LjtbK9L1744srZ7LPVusbqsUrSfpHOay] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][WdY3PjbDE2BadQwaj3zUChF4KE2IXjbU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][MqsaBNy9tMJGljf61RRNn1rweJfu29Pv] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][66Af2OoxaEus6SENc3NA9jK93d9TKbgR] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Vpfjx8xslFdFmOflELOTivJA58fOVmK5] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][YcrXXzJerd5ocP4aMu8QVSodnI4WpcVa] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LjtbK9L1744srZ7LPVusbqsUrSfpHOay] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][MqsaBNy9tMJGljf61RRNn1rweJfu29Pv] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Vpfjx8xslFdFmOflELOTivJA58fOVmK5] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YcrXXzJerd5ocP4aMu8QVSodnI4WpcVa] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][5tjyCBiwJqZgEFpiHFfVJPRslVt0pjY3] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][AqxNWh9OMuCUgKWcWv7WFhlsbxnLRs77] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][NPsi82Lba6V0Y75JaBEwuKS9Bl6dC7Rs] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8Pxc4UCaJRPRkFIUawJC7QBRdhgYmxqk] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][AqxNWh9OMuCUgKWcWv7WFhlsbxnLRs77] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][5tjyCBiwJqZgEFpiHFfVJPRslVt0pjY3] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8Pxc4UCaJRPRkFIUawJC7QBRdhgYmxqk] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][d0zH15G5zM3EOX9psRs7ioOoxoiTiJfQ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][x4cNtRim4sSCUFrKhgywyYoign4ta5SA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][NPsi82Lba6V0Y75JaBEwuKS9Bl6dC7Rs] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][IpnUIIhVrWVRwAFAqwErHZo2ruyU5sp9] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][giqPvPRmbcI3kcGfWisltxAwN5OAfrel] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][x4cNtRim4sSCUFrKhgywyYoign4ta5SA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][d0zH15G5zM3EOX9psRs7ioOoxoiTiJfQ] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][IpnUIIhVrWVRwAFAqwErHZo2ruyU5sp9] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][giqPvPRmbcI3kcGfWisltxAwN5OAfrel] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lL3Lo08I55NXZejlZmyukK8QxDSpvU9h] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][5fPVraaNA4Mv1rckk0SFJE3px2BHtOC2] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YVzizaxQfDSjJ5nTkaZNfHms0FPQL0wD] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][e4U01K1GvMsHRr5jdziJmP6tfq7RrIbp] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][YVzizaxQfDSjJ5nTkaZNfHms0FPQL0wD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][5fPVraaNA4Mv1rckk0SFJE3px2BHtOC2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lL3Lo08I55NXZejlZmyukK8QxDSpvU9h] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][e4U01K1GvMsHRr5jdziJmP6tfq7RrIbp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fwBfMUONFUpmUHgFjvDxyA0WlfpYA9hB] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ks1GNvHzQixSxMI1u7yeeSxh7QJ1eATH] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wKKpAsgF3n0w7mlMrlThGDJ98bI7ktke] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KpgnlLREUcexuwm1DrgGjIVMBMNTnwEp] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][fwBfMUONFUpmUHgFjvDxyA0WlfpYA9hB] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ks1GNvHzQixSxMI1u7yeeSxh7QJ1eATH] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wKKpAsgF3n0w7mlMrlThGDJ98bI7ktke] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KpgnlLREUcexuwm1DrgGjIVMBMNTnwEp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][tn1QMdtrP6hXTwTssn60MUWJxXRv4SLW] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fs7TZpedUXEuhMEAoNxxwCNJmz5LzvxJ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][FVgfCYAkUvWvmpu8rFspvReXv5eCbNtj] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ao4jnq0jtOssuKrnjvLxb9HVp3XIGAjR] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][fs7TZpedUXEuhMEAoNxxwCNJmz5LzvxJ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][FVgfCYAkUvWvmpu8rFspvReXv5eCbNtj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ao4jnq0jtOssuKrnjvLxb9HVp3XIGAjR] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][tn1QMdtrP6hXTwTssn60MUWJxXRv4SLW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Rx9vjE5tiraNivKk4el2CCpSVKL5HfQA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2D5e3bS0CKEAv5xennykjuXY84WmXVwS] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][vmvcTEHOpxazOw1mxzV7MMU7CzwglvDA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4ym7Lst5xE08TisgXHpGKH0NFkMt3a6R] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][2D5e3bS0CKEAv5xennykjuXY84WmXVwS] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Rx9vjE5tiraNivKk4el2CCpSVKL5HfQA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][vmvcTEHOpxazOw1mxzV7MMU7CzwglvDA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4ym7Lst5xE08TisgXHpGKH0NFkMt3a6R] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xzUXVs2i3nOzPwskC60xtmOZPgvTC7pK] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fXp16A8oHK6ogw5e9XBzNAclLtMjaIhO] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][DpHMSBcC0lb3j6BPPINJMSMon0wVgc4V] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][yIVxaWT8mFnQUSQbvyuC700CwEn53RDh] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][DpHMSBcC0lb3j6BPPINJMSMon0wVgc4V] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fXp16A8oHK6ogw5e9XBzNAclLtMjaIhO] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xzUXVs2i3nOzPwskC60xtmOZPgvTC7pK] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][yIVxaWT8mFnQUSQbvyuC700CwEn53RDh] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][p5WWQveb1POCxRQDDiUesIGJyBJlGZhr] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][bzETMWfqDJi8qeiW6vqbjPJx9FGEbjPi] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][bzETMWfqDJi8qeiW6vqbjPJx9FGEbjPi] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hy0YkODVEOwL5Deckg2gFv7y8yvY95VX] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][hy0YkODVEOwL5Deckg2gFv7y8yvY95VX] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][NSA7eSX7mzGCkBduXBmyb3paXn6oM0vp] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][NSA7eSX7mzGCkBduXBmyb3paXn6oM0vp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][NWI17ctX1W0AGEaGoSsvxSazXiafeXyC] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][NWI17ctX1W0AGEaGoSsvxSazXiafeXyC] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2aMDzTQYSEAgWwiAWqZpjZ7izwJBtHVq] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][2aMDzTQYSEAgWwiAWqZpjZ7izwJBtHVq] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4s94i5cocKFCwykTfjYOJrkH7HDIvga8] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][4s94i5cocKFCwykTfjYOJrkH7HDIvga8] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][GWSXFuEXDz3gG9Gqjxcarl7cQe42aSiR] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4XMA6CQfiOzNd2COjPgPWzVvjADmwa4e] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][4XMA6CQfiOzNd2COjPgPWzVvjADmwa4e] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][0qyAzvtjIoopJ65W0149Z8gU1fPjlN1I] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][0qyAzvtjIoopJ65W0149Z8gU1fPjlN1I] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4uyVxKY3uHHWxV69wraKaAoKn610eXlt] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][4uyVxKY3uHHWxV69wraKaAoKn610eXlt] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][SKTmHVB2V5K8vwaWAtmmuQrO76MAITSN] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][SKTmHVB2V5K8vwaWAtmmuQrO76MAITSN] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][V2MWwFtxpgZbFSDNIa2tqcM34OfaBIKd] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][V2MWwFtxpgZbFSDNIa2tqcM34OfaBIKd] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8fBNK1wquMfl6wS0o742iB8JPzaRNrtE] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8fBNK1wquMfl6wS0o742iB8JPzaRNrtE] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][COhUxK20x2rHacfk3Ts1xWOSv5XLiMiY] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][R5i5BasevrZzgp6tTVyYy6hf36yZrMEt] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][R5i5BasevrZzgp6tTVyYy6hf36yZrMEt] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Li2kvd5J4GBumr6N6UIEPT9B094Qs62K] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Li2kvd5J4GBumr6N6UIEPT9B094Qs62K] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pmese6n4qmYOMYpktvQViALW9Umx0F9T] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][pmese6n4qmYOMYpktvQViALW9Umx0F9T] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wyrvs5MKhBBxfoKeUM4MLyBz6ox9SiAI] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][wyrvs5MKhBBxfoKeUM4MLyBz6ox9SiAI] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HPXBIixBcIFGzbhv1TTrThIMEPr4c5yP] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][HPXBIixBcIFGzbhv1TTrThIMEPr4c5yP] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qO2c6OQbZtogD6a3b1f2WkaZxCRxPzVb] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][qO2c6OQbZtogD6a3b1f2WkaZxCRxPzVb] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Q9VOwZaVm2e5nQI2lq8BcZrtwKhgW1A0] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][p5WWQveb1POCxRQDDiUesIGJyBJlGZhr] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hDCAR71WY2p48cG1kP5wJ1rtazqQ7q8I] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][hDCAR71WY2p48cG1kP5wJ1rtazqQ7q8I] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Od7blhP8AlJwtV4iZTefKQFoPjU9x5Gt] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Od7blhP8AlJwtV4iZTefKQFoPjU9x5Gt] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8KCbIJSOUGwMQCI1LNZVSpuuIvNwjVY7] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8KCbIJSOUGwMQCI1LNZVSpuuIvNwjVY7] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3G0N1FQ2AXXUzyNxVmY58DYi5cIrDhbV] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][3G0N1FQ2AXXUzyNxVmY58DYi5cIrDhbV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lL71nELw9fC7sLUksfCbgaShbaKfHzMw] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][lL71nELw9fC7sLUksfCbgaShbaKfHzMw] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][FfufI2DsWCUnI91aMENMtPYcqmSu2xDM] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][FfufI2DsWCUnI91aMENMtPYcqmSu2xDM] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][A9GklDb5ywvrWBC6vMJ1Wi2iO5WIrkoY] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][GWSXFuEXDz3gG9Gqjxcarl7cQe42aSiR] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][COhUxK20x2rHacfk3Ts1xWOSv5XLiMiY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Q9VOwZaVm2e5nQI2lq8BcZrtwKhgW1A0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][A9GklDb5ywvrWBC6vMJ1Wi2iO5WIrkoY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8duY2mcOQGa5z8LgHFjRmh7ALrjdBJk0] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OrIv76OqUI6KBXrzMPEWfy98mEZ68fsZ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][AixvTvBMfHFT0EHe1aJBDeDXkXnQr5ej] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][o6SIB9UdD9U4t8LpYXEbly0hwg3LYIp6] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8duY2mcOQGa5z8LgHFjRmh7ALrjdBJk0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OrIv76OqUI6KBXrzMPEWfy98mEZ68fsZ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][o6SIB9UdD9U4t8LpYXEbly0hwg3LYIp6] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][AixvTvBMfHFT0EHe1aJBDeDXkXnQr5ej] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KY3hLX8HgBGuUEhaDyMLCjgf8mwV1u2k] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UODrIa1vFzejV5ksbu5Ed53MsVXYnrzZ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][k3Ns7sLQFnXjctVFEfEuqVCoo80MTjq3] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][VdtoBmTxfcqae0W6UDQNag9CMC97Yipo] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][KY3hLX8HgBGuUEhaDyMLCjgf8mwV1u2k] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UODrIa1vFzejV5ksbu5Ed53MsVXYnrzZ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][k3Ns7sLQFnXjctVFEfEuqVCoo80MTjq3] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][VdtoBmTxfcqae0W6UDQNag9CMC97Yipo] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Ia74YLLSRebYqYlbTswmxe0hnaQKSVGR] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UOPfgiPy9KHlZh5FYT2tthExTzlwfVXD] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ugpblUe1ZNgKZ1LcWMj1U18Zew46RTuS] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][iHfF5YYSJiPNvPRdS3oTDTAxovB7w7yV] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Ia74YLLSRebYqYlbTswmxe0hnaQKSVGR] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][UOPfgiPy9KHlZh5FYT2tthExTzlwfVXD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ugpblUe1ZNgKZ1LcWMj1U18Zew46RTuS] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ClFUfViZRh6qK3C9QCvOAtXs5ONX1hSf] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][iHfF5YYSJiPNvPRdS3oTDTAxovB7w7yV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][d9y8c34FxW9LB7nV5akAL5B7F9xGIOYZ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YMqsLU1ecxrmdqPo1EzGQpFJCYORBWTi] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][zNhg4umWg7YsXeTSXCoNTMNTjP2sS3Kq] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ClFUfViZRh6qK3C9QCvOAtXs5ONX1hSf] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YMqsLU1ecxrmdqPo1EzGQpFJCYORBWTi] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][d9y8c34FxW9LB7nV5akAL5B7F9xGIOYZ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][uGS76FSvPN9qr5PAqsY1rL1kMUM1Jmp1] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][uGS76FSvPN9qr5PAqsY1rL1kMUM1Jmp1] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][CZvRtISywhhhmcKPj9FicsgSULc7HWGQ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][CZvRtISywhhhmcKPj9FicsgSULc7HWGQ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][kblcJwezEBxS0cF7M4ExKoF538H8oJGh] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][kblcJwezEBxS0cF7M4ExKoF538H8oJGh] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZW2qZo1Oo8ATjS3lqrWBWANC4BTzEBPt] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ZW2qZo1Oo8ATjS3lqrWBWANC4BTzEBPt] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7vJCvqdrvSFK0qmR0ydw2iUagnNbOIHJ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][7vJCvqdrvSFK0qmR0ydw2iUagnNbOIHJ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][sQn4w2kTJFWsOTArKVjppThA5dF7d74Y] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][zNhg4umWg7YsXeTSXCoNTMNTjP2sS3Kq] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][rsEbk15i00te8PLjmkiJwMfkobObfIuo] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][rsEbk15i00te8PLjmkiJwMfkobObfIuo] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QNQ5CVO5gbAZiuzmKtfZ9v9H1kPsguqR] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][QNQ5CVO5gbAZiuzmKtfZ9v9H1kPsguqR] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][unfkEARQArMHHemOD6S7AfCQbJk2y5GV] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][unfkEARQArMHHemOD6S7AfCQbJk2y5GV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8h5RtCC1wgzvxrKOoeoeev3uvectLaF8] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8h5RtCC1wgzvxrKOoeoeev3uvectLaF8] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][H7YWo2aJ9sGvz4uZv4eWABxqguFM1BBV] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][H7YWo2aJ9sGvz4uZv4eWABxqguFM1BBV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][XzRTDwXhqAO9ZaMwqGG6kQIWDaCE8pla] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][XzRTDwXhqAO9ZaMwqGG6kQIWDaCE8pla] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pSJNSCJS01oA1gJ4Dr08yoM4B0mkjlPq] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][pSJNSCJS01oA1gJ4Dr08yoM4B0mkjlPq] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][PKUKKAyBxWEODLwNsoWBNnZuiCB6BTA2] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][PKUKKAyBxWEODLwNsoWBNnZuiCB6BTA2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][cLGz3fvFlAH8cGXMGbbZ6Z2AGVnP5uaO] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][cLGz3fvFlAH8cGXMGbbZ6Z2AGVnP5uaO] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][eH4qVB2XykfV11Wfj6rxeAZ14KhMhuQ5] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][eH4qVB2XykfV11Wfj6rxeAZ14KhMhuQ5] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][incjFam1L0sumeL7R2Q09O1oLcnbti2l] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][incjFam1L0sumeL7R2Q09O1oLcnbti2l] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jOf5YCkIKmBdW4e2qrSlGXWgBz3m4osJ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][jOf5YCkIKmBdW4e2qrSlGXWgBz3m4osJ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hBPMSdlZ8haBk8oNSYgczziFEjFp25RK] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][hBPMSdlZ8haBk8oNSYgczziFEjFp25RK] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][i0bP1n5q6NOnn6FZjn3EGP8MgswQaMDM] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][i0bP1n5q6NOnn6FZjn3EGP8MgswQaMDM] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][91DPYhlk87qwdQaehKxcbbct7h8RdpLL] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][91DPYhlk87qwdQaehKxcbbct7h8RdpLL] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][mzHYJkkBnFr9CHiZf52nRjxtLPCOzDeL] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hadwLLy27jHJ0q9WEcIUJYAa7PxJW0g6] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ss9TUMpra7apmPhyf5r0RtlYb8HKexML] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][sQn4w2kTJFWsOTArKVjppThA5dF7d74Y] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hadwLLy27jHJ0q9WEcIUJYAa7PxJW0g6] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][mzHYJkkBnFr9CHiZf52nRjxtLPCOzDeL] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ss9TUMpra7apmPhyf5r0RtlYb8HKexML] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][1HntAOOj7cZuq5VOQfSPXw27cfj7hNCj] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][rBtTaWSiVPp1pyP3XUL5DgxxjipHtEBf] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][NHfnkplUZZF0J95a7cun55rUZrZah1HB] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UfFJUg5GNEqutVy8KY6tx9LiKh1BqYiM] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][1HntAOOj7cZuq5VOQfSPXw27cfj7hNCj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UfFJUg5GNEqutVy8KY6tx9LiKh1BqYiM] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][rBtTaWSiVPp1pyP3XUL5DgxxjipHtEBf] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][NHfnkplUZZF0J95a7cun55rUZrZah1HB] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HhVuFHqZPILqg0pgpeUPf9MlMgzpYv6T] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][5vdapMpuEs2occUGMVZkoA2VzrpB4i8W] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2hL7S7eqwd5ZgTtO9D2D5Vxfbtf3OoL2] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][aW2sLhuXtw7LdWFkv5K5L5q4pQ5Qt6P2] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][2hL7S7eqwd5ZgTtO9D2D5Vxfbtf3OoL2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][5vdapMpuEs2occUGMVZkoA2VzrpB4i8W] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HhVuFHqZPILqg0pgpeUPf9MlMgzpYv6T] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][aW2sLhuXtw7LdWFkv5K5L5q4pQ5Qt6P2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Hom1Y7cYX4ycPHGpOt017I0cI7V9AySK] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][dkiCnP9nIsdzB8hVmUYZ7pyI38GqpiHI] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][oFhbwWRKPFq0iewTqHC66jWQ6z5Aw0L0] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][sgCwCkEx9cNHUiohxH8iWUjdO5saQ7Db] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Hom1Y7cYX4ycPHGpOt017I0cI7V9AySK] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][dkiCnP9nIsdzB8hVmUYZ7pyI38GqpiHI] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][oFhbwWRKPFq0iewTqHC66jWQ6z5Aw0L0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][sgCwCkEx9cNHUiohxH8iWUjdO5saQ7Db] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][n43TZp4JgrwHJCpq8WxmYP94fd1Z7tM1] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][yQIoU9dw6zvrEqtiqQduS2lCsqHxTMYX] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ix14M6fnmp1DjXbLqhPFS6toK3jLVIdG] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UMWE6uhsTvKuwngowbeAYMiI9N1AYj8X] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][n43TZp4JgrwHJCpq8WxmYP94fd1Z7tM1] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][yQIoU9dw6zvrEqtiqQduS2lCsqHxTMYX] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ix14M6fnmp1DjXbLqhPFS6toK3jLVIdG] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HSP0Ho466vpIZpllBPPaIEq0mfBm5NI0] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UMWE6uhsTvKuwngowbeAYMiI9N1AYj8X] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][TOGTdlwu6h8qWoKkrfHqmWO68TdWQ69J] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hVZONLF6QEOHqruUKGNKOSNFneAGjgZd] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][kYOvvpAcq4QbMEqvxaiVwtnjl8rJ2Nwn] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HSP0Ho466vpIZpllBPPaIEq0mfBm5NI0] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][TOGTdlwu6h8qWoKkrfHqmWO68TdWQ69J] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][hVZONLF6QEOHqruUKGNKOSNFneAGjgZd] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][6HJJs5eMbadxPNSlU6j7yFtgTKpI95KL] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][kYOvvpAcq4QbMEqvxaiVwtnjl8rJ2Nwn] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][KqfrFKnRl7nDdxLlNkbRUcJuM631pdb8] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qtgZmRJNMxbZRjN0WZURH7oZr8Bnecru] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][6HJJs5eMbadxPNSlU6j7yFtgTKpI95KL] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xetDm74StQFTsQAxKcBWLNRPIOSdToe6] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][qtgZmRJNMxbZRjN0WZURH7oZr8Bnecru] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KqfrFKnRl7nDdxLlNkbRUcJuM631pdb8] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8oCOO4M7p7YzRjb2sZdsvecwKJ6oHxk1] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fsQaDHkZ3NUiTrkE7Jv842YPXvqFjdzV] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][vgr920xTAB8GLSbsuR59iQ7ZkQvjzukb] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xetDm74StQFTsQAxKcBWLNRPIOSdToe6] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][DgprDeNpPIQilJBMDARenVFZ10tyHN0e] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8oCOO4M7p7YzRjb2sZdsvecwKJ6oHxk1] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fsQaDHkZ3NUiTrkE7Jv842YPXvqFjdzV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][vgr920xTAB8GLSbsuR59iQ7ZkQvjzukb] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][vdGRgXi8HX4T5L8TYeHpPcmZuR2S1g4C] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7oass1xg2YnMUAELQIFeKQIx52cuWDtp] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][DgprDeNpPIQilJBMDARenVFZ10tyHN0e] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZKqJSJHhfIg4qFJdtmwgKkIE8iSlAqCL] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][9JRjPpYKbMTDy0Xzn5Tg3QBNBMR5P5a7] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][vdGRgXi8HX4T5L8TYeHpPcmZuR2S1g4C] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZKqJSJHhfIg4qFJdtmwgKkIE8iSlAqCL] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][u6PolnwfCzPrGDU1P3sLxupPpTgkP3W5] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][u6PolnwfCzPrGDU1P3sLxupPpTgkP3W5] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][CGPTIKNHOll6ToMCVLtM7iH0F7GfhIhi] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][CGPTIKNHOll6ToMCVLtM7iH0F7GfhIhi] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8qACSgbf02Tj3wqdJ1W0CwRAb9lMonXO] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8qACSgbf02Tj3wqdJ1W0CwRAb9lMonXO] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OO3PznB24Bjg1IumuoUrVgpc1FVkQKe5] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][OO3PznB24Bjg1IumuoUrVgpc1FVkQKe5] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][WDOH40KpcSPuPihutNTXuF9HvBlUE2nF] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][WDOH40KpcSPuPihutNTXuF9HvBlUE2nF] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][xjSRS141NWuT69ciCifkp5COOSRbXxpW] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][xjSRS141NWuT69ciCifkp5COOSRbXxpW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][fmXSH2oYkqsfHmBGgBtXx1eTSnz0AvHU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][fmXSH2oYkqsfHmBGgBtXx1eTSnz0AvHU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][duLTgaZIQWstyhodferUtxQZHwNqCvyS] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][duLTgaZIQWstyhodferUtxQZHwNqCvyS] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Yhkn1no7yoKrxMxcZQQiibXP3054hqO6] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Yhkn1no7yoKrxMxcZQQiibXP3054hqO6] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][o0Cf295aKl8MCrUFqWUIXpDC2ecOLZzd] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][o0Cf295aKl8MCrUFqWUIXpDC2ecOLZzd] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4QXpNHscJassMu0MexyoPq25XbBoxorj] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][4QXpNHscJassMu0MexyoPq25XbBoxorj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3YhZUOqCr6X5NNIJAhxdX23oAoPeWYW9] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][3YhZUOqCr6X5NNIJAhxdX23oAoPeWYW9] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][96tgrFYVAlzeZwxOwlYdMpxQHslJDMjw] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][96tgrFYVAlzeZwxOwlYdMpxQHslJDMjw] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7oass1xg2YnMUAELQIFeKQIx52cuWDtp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][6s7x0gf4U7EO43TNUEOWgOnu9gyZPU9N] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][6s7x0gf4U7EO43TNUEOWgOnu9gyZPU9N] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][JgMfJ3C5QRRBRKxQZGRCqLHAmoa8Qnmz] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][JgMfJ3C5QRRBRKxQZGRCqLHAmoa8Qnmz] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KcIOIPegSF9I8dRviqof9A5XrL5h98LU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][KcIOIPegSF9I8dRviqof9A5XrL5h98LU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][MrrpKZ0Xdn6iGsvzu8r1kstr0bjrbwzG] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][MrrpKZ0Xdn6iGsvzu8r1kstr0bjrbwzG] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][mpQGao0bczDrrmp6V9BsoYyYqI5Xi2NP] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][mpQGao0bczDrrmp6V9BsoYyYqI5Xi2NP] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][wVek7tqtb2HgyTGwFRWHl7dQyudqCTBn] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][wVek7tqtb2HgyTGwFRWHl7dQyudqCTBn] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4JoJBHBZPM9eeYjTz2VyJlDPXpGYDxMR] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][9JRjPpYKbMTDy0Xzn5Tg3QBNBMR5P5a7] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][WCfpSPnpxidsT3j7zL6nZsawV50ASe0Z] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][WCfpSPnpxidsT3j7zL6nZsawV50ASe0Z] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][E6PxjMBE4O4PUpYTjUW1VJxXPu6WCN2G] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][E6PxjMBE4O4PUpYTjUW1VJxXPu6WCN2G] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][p4fd9IJx70CTpVTAMp2Dl4UhHYv0E24s] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][p4fd9IJx70CTpVTAMp2Dl4UhHYv0E24s] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][qcBMeCKbeuxB9oIuoe0KZgWnHZrXKKtU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][qcBMeCKbeuxB9oIuoe0KZgWnHZrXKKtU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KIeYBLbImYWY3PR4tuzO2SWxUBfifZAX] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][KIeYBLbImYWY3PR4tuzO2SWxUBfifZAX] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QPnSnltgCPiPqRsyhDc9L3pTSJeflqR1] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][QPnSnltgCPiPqRsyhDc9L3pTSJeflqR1] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Jan5fFKzrJH09KdqgjMnTz17Wc27tPL1] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][zyJcj0CI8DHzC0F0ecxoZmK7U8rHsdUK] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][4JoJBHBZPM9eeYjTz2VyJlDPXpGYDxMR] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4Dssn4tQhAyTCRt29oqN0onkX9Wotb7p] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][aTYt6sXbNGmFk1wfe9uKRCui33mu7T2m] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Jan5fFKzrJH09KdqgjMnTz17Wc27tPL1] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][zyJcj0CI8DHzC0F0ecxoZmK7U8rHsdUK] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][i6KYs4Ry82ysc8vf2ewQfeF2dGIawSaD] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4Dssn4tQhAyTCRt29oqN0onkX9Wotb7p] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][aTYt6sXbNGmFk1wfe9uKRCui33mu7T2m] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KmR6mvQW1e6z2QY3argO3Fj0BikVmoD6] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][IRxDBwEn4QTwmItMqcKNMcAGp3tSahKU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][VL6fbq7zvtTWD41d6BRJjOlo0vJukqnP] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][i6KYs4Ry82ysc8vf2ewQfeF2dGIawSaD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][KmR6mvQW1e6z2QY3argO3Fj0BikVmoD6] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][clbvIukEtVD7oZCImHf2ve0Q5Sjt4rVM] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][VL6fbq7zvtTWD41d6BRJjOlo0vJukqnP] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][IRxDBwEn4QTwmItMqcKNMcAGp3tSahKU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][R0PcZVMPQoHSSbnEav4r4ehner68raeD] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][j2iOJ5Dm65aCxVe9YcHlaLj7axzZDP5k] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LkSSqRc47q7O1AQlPAHM227Lcv9bvYNB] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][clbvIukEtVD7oZCImHf2ve0Q5Sjt4rVM] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][R0PcZVMPQoHSSbnEav4r4ehner68raeD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][j2iOJ5Dm65aCxVe9YcHlaLj7axzZDP5k] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][FEB7nU0CMWxY8K94Q9izApJWxCTt7NbC] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][LkSSqRc47q7O1AQlPAHM227Lcv9bvYNB] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4YXWZXeYv08wEZQmfmv8D7I290FX3fNT] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Ct5IksC1YpP7naG0KjWgWZNtZXKcncaW] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Ea7QJbbrA5dujvIclaurqi2lFeFn0lUt] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][FEB7nU0CMWxY8K94Q9izApJWxCTt7NbC] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][4YXWZXeYv08wEZQmfmv8D7I290FX3fNT] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Ct5IksC1YpP7naG0KjWgWZNtZXKcncaW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Ea7QJbbrA5dujvIclaurqi2lFeFn0lUt] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][6dvN81aVTYduaYYVQggg9kCk19BYiSxV] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][HQspRHat61XrcmtIflUIGRjVQQA6r23W] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][WkJxBVKVGZyuGYuesjHxwjsiO4LgMSWT] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][9RE8EwAL4tjwHAANkv9UTiplA02xtoYQ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][HQspRHat61XrcmtIflUIGRjVQQA6r23W] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][WkJxBVKVGZyuGYuesjHxwjsiO4LgMSWT] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][6dvN81aVTYduaYYVQggg9kCk19BYiSxV] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][9RE8EwAL4tjwHAANkv9UTiplA02xtoYQ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ZvsrzvTZxc67QDm908czO0y1mkpoGu4e] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pmhQCEDRAiA9BbM4PaWDKQxDy5ofsJaP] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][cm3tRR7ANClUAp3IicQ06lQrwzSNwAJ3] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7TCoQ6ZeXdY0pN1bfnnuwz2jjHwd5RzZ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ZvsrzvTZxc67QDm908czO0y1mkpoGu4e] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][cm3tRR7ANClUAp3IicQ06lQrwzSNwAJ3] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][7TCoQ6ZeXdY0pN1bfnnuwz2jjHwd5RzZ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][pmhQCEDRAiA9BbM4PaWDKQxDy5ofsJaP] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][tciizt3IxW8iu3LIBUmEoImapE6Ix7gX] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ho3RZVezBY42cwQMv9c7eF4PgTDLdmzc] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][JP8XLga34B1wqFvXMnqivbEhXMh5YNTW] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][j9kxJLI4xMvCWpva7GMKpEecOUTqeujx] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][tciizt3IxW8iu3LIBUmEoImapE6Ix7gX] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ho3RZVezBY42cwQMv9c7eF4PgTDLdmzc] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][JP8XLga34B1wqFvXMnqivbEhXMh5YNTW] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][GSeW1Kzj3LzKEey576u3dA8e9XfNV3i6] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jehrVVPhrFHxQXg1qhAGCzjlJuv7K7zA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][j9kxJLI4xMvCWpva7GMKpEecOUTqeujx] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][iYLn8TxRkcWCCpbl61gVHf8mjhFzOlT3] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][cLiNsorij3r9e7xfXgkxzrcMT6XEdxeh] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][iYLn8TxRkcWCCpbl61gVHf8mjhFzOlT3] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jehrVVPhrFHxQXg1qhAGCzjlJuv7K7zA] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][GSeW1Kzj3LzKEey576u3dA8e9XfNV3i6] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][XLJ4gWk19yK62g4Tmj7eiGXNGZvh1oHO] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][cLiNsorij3r9e7xfXgkxzrcMT6XEdxeh] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][N863GNByCqO2fbHcz6wYNYysXw0vbMak] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Yyg541wP37WpYufcvNIgbbN4raEUEvc5] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QJAuXGKQDnz0ltth7jtaJRd6c2Jk7YRh] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][XLJ4gWk19yK62g4Tmj7eiGXNGZvh1oHO] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][N863GNByCqO2fbHcz6wYNYysXw0vbMak] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][Yyg541wP37WpYufcvNIgbbN4raEUEvc5] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][84Yl47EyUJy6WxwLIWBzFV5GODDshW2p] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][8v6v1ZqMcPmEpzGkOBmLvxD3YjvltAmU] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QJAuXGKQDnz0ltth7jtaJRd6c2Jk7YRh] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][aIIUkD59eyGedbaQPlgqNdqH7h4zH5Ov] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][a6a1dX6ySSAL2t2iYT4h2qjPDof9eTxh] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][8v6v1ZqMcPmEpzGkOBmLvxD3YjvltAmU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][84Yl47EyUJy6WxwLIWBzFV5GODDshW2p] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][aIIUkD59eyGedbaQPlgqNdqH7h4zH5Ov] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][3vdoQPwV8uOv1PH2ow0AghGG14xogHLQ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][v7EnH34Q44VwAdajzH49ntRgqlPt2NhE] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][takbrhqrlI0XUWwtjzuvpWadkQuqNnSc] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][a6a1dX6ySSAL2t2iYT4h2qjPDof9eTxh] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][v7EnH34Q44VwAdajzH49ntRgqlPt2NhE] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3vdoQPwV8uOv1PH2ow0AghGG14xogHLQ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][4eRvIY4pbCuxgskf5E8KeXAuIyM7oAnT] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][takbrhqrlI0XUWwtjzuvpWadkQuqNnSc] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][oHd9D4N8Lvc7q4ZftlLpMxlCxvmReIgA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OKMynActPGeGH9VZZgLKdDTj4PLble8B] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UNYY0p5Y6HcdktJHgQfKsHTiXosQqcLT] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][oHd9D4N8Lvc7q4ZftlLpMxlCxvmReIgA] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][4eRvIY4pbCuxgskf5E8KeXAuIyM7oAnT] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][6eIxzQtaNQZUdHP5r5mxRvwJ7Ze3J9oW] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][OKMynActPGeGH9VZZgLKdDTj4PLble8B] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][13BoBGGX8dve6Y0Yyt9v18fqIOsXxDHL] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UNYY0p5Y6HcdktJHgQfKsHTiXosQqcLT] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][f8wJVZxE2Ysn7stGtogWO01IkypxfQE4] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][XBaCU1iksx4YWNxdhe9b2qPZpnRALB8v] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][6eIxzQtaNQZUdHP5r5mxRvwJ7Ze3J9oW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][13BoBGGX8dve6Y0Yyt9v18fqIOsXxDHL] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][f8wJVZxE2Ysn7stGtogWO01IkypxfQE4] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][UEnhVt0zp3QUMm6An8tW4kmiV0aJFLvG] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][0cBElHrpDoL4VRQQTFRl8kFjpOXAzKwy] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][mLEMOErANQpAed3H1WAbEYTMhCc6uQFD] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][XBaCU1iksx4YWNxdhe9b2qPZpnRALB8v] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][UEnhVt0zp3QUMm6An8tW4kmiV0aJFLvG] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][mLEMOErANQpAed3H1WAbEYTMhCc6uQFD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][SLWsBGFTMc8vMVyuiLQP4vs6cVfAz88x] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][0cBElHrpDoL4VRQQTFRl8kFjpOXAzKwy] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][11kTZ64st2tLgPQXqYN4a8IhLHadVEHW] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][NJdmtx1sITkRaeH8ES9dARfa1LAUmoyQ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][yPH6WiNZrVJOr9mnsBIYNwbXy8wQLA2g] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][11kTZ64st2tLgPQXqYN4a8IhLHadVEHW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][SLWsBGFTMc8vMVyuiLQP4vs6cVfAz88x] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][RWnDoq7u89PAsE4PmPAoBzxJjW0tUPnJ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][yPH6WiNZrVJOr9mnsBIYNwbXy8wQLA2g] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][mkCuusCt2WL1ImL3r57vIFn0RzTBO3hg] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][NJdmtx1sITkRaeH8ES9dARfa1LAUmoyQ] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][QdLEAD6e7FqiH30QTGmnrwhRkoBem4sw] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][RWnDoq7u89PAsE4PmPAoBzxJjW0tUPnJ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][t72Z1ODSB4UBKq5W4RCBKpUTVRK2TH3h] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][mkCuusCt2WL1ImL3r57vIFn0RzTBO3hg] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][IJveyiBEcPPZTQ5T3R1WsONN6hm60BZS] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][QdLEAD6e7FqiH30QTGmnrwhRkoBem4sw] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][t72Z1ODSB4UBKq5W4RCBKpUTVRK2TH3h] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3WyqrxGDygszodeOs7kVNGjNFl7vAJzE] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][IJveyiBEcPPZTQ5T3R1WsONN6hm60BZS] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][B3xc6LmeDiPe5nWIuogDJrCsR1HEYq37] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][C7joIYLq0zhWJW7Fc2ZpnpeODgQMqjUQ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][g06mVvLb8Gua7Xh1ExssUcnnNi42OWvE] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3WyqrxGDygszodeOs7kVNGjNFl7vAJzE] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][x9MgmPVBCK4cLL4VjqtWmCoHPTjnrRsN] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][B3xc6LmeDiPe5nWIuogDJrCsR1HEYq37] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][g06mVvLb8Gua7Xh1ExssUcnnNi42OWvE] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][C7joIYLq0zhWJW7Fc2ZpnpeODgQMqjUQ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][YAC9HSO9IBXxEUSBNytrwKSHLkc0VM06] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][dwNsIkqVmHDJ3tKxDRsEda3BlM8S0DUS] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][MwXh7mIhr3AWUNKBe41ONG6GWutnmlht] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][x9MgmPVBCK4cLL4VjqtWmCoHPTjnrRsN] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][YAC9HSO9IBXxEUSBNytrwKSHLkc0VM06] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][dwNsIkqVmHDJ3tKxDRsEda3BlM8S0DUS] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][2H7jFyaZgTj5RxehDgtLiXGne7h7612P] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Os4IjsHTXmL5Dq0lCFaeAAnTcTZXdQzI] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][MwXh7mIhr3AWUNKBe41ONG6GWutnmlht] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ilEuUeb5JRYnjx4ejslDQ7qq3pQh7b01] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][2H7jFyaZgTj5RxehDgtLiXGne7h7612P] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][Os4IjsHTXmL5Dq0lCFaeAAnTcTZXdQzI] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][3pqu8Uh88yhug2QoRp6PAyfK7Zx3OkJZ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][lZwNH02jUNM1so030OeSCRRIYsKUxCT8] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ilEuUeb5JRYnjx4ejslDQ7qq3pQh7b01] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][BB0YQi8CHcijmVWWPhxaiu52Ef9tSaS1] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][iNNZ1WP6wi6Zq5bMOz3xSShvAwYDR2m2] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][lZwNH02jUNM1so030OeSCRRIYsKUxCT8] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][3pqu8Uh88yhug2QoRp6PAyfK7Zx3OkJZ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][BB0YQi8CHcijmVWWPhxaiu52Ef9tSaS1] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][fmVuBrEE4ZnoT0N2NT3mfX6qly5FHmzy] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][iNNZ1WP6wi6Zq5bMOz3xSShvAwYDR2m2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ngNUvQQpXJtzW2y7P8buTktlfymBbjRi] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][tVX0HVzWZHRPOAth31m2lWjKMFSNWPGR] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][KWsGdyAmLpNtcaPvL7xS7jmBTBFQBWcM] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][tVX0HVzWZHRPOAth31m2lWjKMFSNWPGR] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][nStpHdcIOAy7s6vxDf4OXEVklnB1Vivp] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][nStpHdcIOAy7s6vxDf4OXEVklnB1Vivp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][ANlUrwV30v6r7jfFmiRukbs2p4z7Y8E0] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][ANlUrwV30v6r7jfFmiRukbs2p4z7Y8E0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][OYrBLsFCv0Ob7adzsRG1J59DMO2LjjhU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][OYrBLsFCv0Ob7adzsRG1J59DMO2LjjhU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][sGAIMcCZz0YVMjdxoQsYP0OSzLVOIpTA] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][sGAIMcCZz0YVMjdxoQsYP0OSzLVOIpTA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][nOoVVw6ryBLUNbgYnfQEuEU2RBY9O1TR] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][nOoVVw6ryBLUNbgYnfQEuEU2RBY9O1TR] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][krxgB9ffAHQYdmmHIRC3DmIUahRgQTHu] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][krxgB9ffAHQYdmmHIRC3DmIUahRgQTHu] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][ublmJzd02IuZYNlmVpaWQkN47dhZi3Nn] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][ublmJzd02IuZYNlmVpaWQkN47dhZi3Nn] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][dCnGF5h79hr075yrlYQ9OztgRTCcHsVu] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][dCnGF5h79hr075yrlYQ9OztgRTCcHsVu] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Ahpio6XMOcCB3EzfW7isroGdKWik07Pw] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][Ahpio6XMOcCB3EzfW7isroGdKWik07Pw] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][ngNUvQQpXJtzW2y7P8buTktlfymBbjRi] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][jRmXVHcS4GF1KBbXB6hxt201Ijm5aRKY] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][jRmXVHcS4GF1KBbXB6hxt201Ijm5aRKY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][BLisJWyqi69BBOmevPhfkhdhpXpqPYKC] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][BLisJWyqi69BBOmevPhfkhdhpXpqPYKC] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][nXtlX4OJ67Z4z9Qd2wMbpcKBmdWwcMM2] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][nXtlX4OJ67Z4z9Qd2wMbpcKBmdWwcMM2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][CFOJf4O4clRJACgFwmJ8paXD0yktrlHC] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][CFOJf4O4clRJACgFwmJ8paXD0yktrlHC] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][ucJo5vpVmCKbUvJc5162VXnae9ecj8Ae] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][ucJo5vpVmCKbUvJc5162VXnae9ecj8Ae] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][CW60CRyI8I00rHFWxHPcW972SAEvYgtJ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][CW60CRyI8I00rHFWxHPcW972SAEvYgtJ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Wgx6qPYTh0QcKwhU1VNI5jS0Qvpj3VLI] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][Wgx6qPYTh0QcKwhU1VNI5jS0Qvpj3VLI] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][56byeEwyC1ikk5HotdA4xkSLRKzjK6zY] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][56byeEwyC1ikk5HotdA4xkSLRKzjK6zY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][btYwbfm7zOt2Ey6XBrqQZ8xWtRFx12EN] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][fmVuBrEE4ZnoT0N2NT3mfX6qly5FHmzy] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][78uTDWQW4bvsxRc9cxLFrXFZPrC1BHHp] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][78uTDWQW4bvsxRc9cxLFrXFZPrC1BHHp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][rBjpJPHeAOdAnDDy5ql5pL8Gow7QHYFC] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][rBjpJPHeAOdAnDDy5ql5pL8Gow7QHYFC] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][poR57LSx8CIwJtyOgy3herqRt4NNwlOX] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][poR57LSx8CIwJtyOgy3herqRt4NNwlOX] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][fczD8DTj67iqlcJW8mn8b6snjTGsS7uy] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][fczD8DTj67iqlcJW8mn8b6snjTGsS7uy] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][PjGmo8z09BfGjEjQu9G08hyfLZOE1Rwn] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][PjGmo8z09BfGjEjQu9G08hyfLZOE1Rwn] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][5jAsLUKALa8TQ2bCdRZP26P2mT2X7o0i] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][5jAsLUKALa8TQ2bCdRZP26P2mT2X7o0i] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][EIfoUmdhidAh7IIB33sjW32600kVyqbV] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][EIfoUmdhidAh7IIB33sjW32600kVyqbV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][iZznEA10eLhBUigiYZ585FArpIRl52Xr] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][iZznEA10eLhBUigiYZ585FArpIRl52Xr] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][gfxgZF1EbiKKFXgJQqdeNfU8oAdlAMKy] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][gfxgZF1EbiKKFXgJQqdeNfU8oAdlAMKy] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][pLRIVEMoNDzo5Jo9LQWZeA1s3Vci6qKx] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:32][KWsGdyAmLpNtcaPvL7xS7jmBTBFQBWcM] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:32][DwXwRAXJUHjSoGNalxEpluTKJgEyPURh] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][DwXwRAXJUHjSoGNalxEpluTKJgEyPURh] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][9RQy8tz0X6ECqR7Te6u0hH6V6WmzFMQZ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][9RQy8tz0X6ECqR7Te6u0hH6V6WmzFMQZ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][9XMeIrH6MsvRgofrt8jtO7W7Mko6vvYD] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][9XMeIrH6MsvRgofrt8jtO7W7Mko6vvYD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][3S02GwMa1QjIqGBuw4pnKKBIlAF7xHPo] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][3S02GwMa1QjIqGBuw4pnKKBIlAF7xHPo] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][nYQ8pB4HJ1nsDwZZyv7tTBZfrqj3x1db] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][nYQ8pB4HJ1nsDwZZyv7tTBZfrqj3x1db] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][ZVvFaYB5Zll8Ni3N8wXTmys0pTMuKXLD] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][ZVvFaYB5Zll8Ni3N8wXTmys0pTMuKXLD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][pazSyCAIYavUD3ftGf2pdctwXHbQgCmQ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][pazSyCAIYavUD3ftGf2pdctwXHbQgCmQ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][4JorC3Nv6xPL9ZIs3AyllPaWeWmztoHj] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][4JorC3Nv6xPL9ZIs3AyllPaWeWmztoHj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][ioJLCCKftwiPAlSOOTju1UmvrTZ6zlRj] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][btYwbfm7zOt2Ey6XBrqQZ8xWtRFx12EN] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][pLRIVEMoNDzo5Jo9LQWZeA1s3Vci6qKx] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][3sbDeDkGY3CWCLaLpZUWQEAqr7DjccXq] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][xcONH8YGZDzs3KMzXHeSk23nrYYn6U5s] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][NV00qC4SsGud8FXh0rS09kMcaZACPf0o] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][ioJLCCKftwiPAlSOOTju1UmvrTZ6zlRj] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][3sbDeDkGY3CWCLaLpZUWQEAqr7DjccXq] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][XdUwjTgxxkSFyyTwBTnHY8qF19hBUxd4] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][NV00qC4SsGud8FXh0rS09kMcaZACPf0o] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][xcONH8YGZDzs3KMzXHeSk23nrYYn6U5s] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][KjVBpDfLE5RGbTZnSNGX8mVw0xX1SXEF] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][UTBxurIWPIBI7wK0zmwCW8gXkszqAyUk] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][xL8FW2PzDYqD1zaOE9A4pYiq7r54nuIp] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][XdUwjTgxxkSFyyTwBTnHY8qF19hBUxd4] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][KjVBpDfLE5RGbTZnSNGX8mVw0xX1SXEF] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][tMhgIUjqLvvbDpVSedzMLFvpIReQBPId] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][UTBxurIWPIBI7wK0zmwCW8gXkszqAyUk] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][xL8FW2PzDYqD1zaOE9A4pYiq7r54nuIp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][neF4hIw7Rz9TbW9SrU3DcEA1LoO3EXDJ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][bLA3SGnJTO57KVIjYniG5bAhZvCnrWo5] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][DDM2R1qGW3mxbwQl3sXtTlRA5940dHj6] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][tMhgIUjqLvvbDpVSedzMLFvpIReQBPId] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][neF4hIw7Rz9TbW9SrU3DcEA1LoO3EXDJ] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][pYAt90YboJpc8bfHoNc0HHcrFJyQqCDi] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][bLA3SGnJTO57KVIjYniG5bAhZvCnrWo5] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][wuEi0pJnFged1l4UvPO9LcevtxIMj92m] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][DDM2R1qGW3mxbwQl3sXtTlRA5940dHj6] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][ZkPqssLzVoCpROFQHkk1W7ponrdd7eOp] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][PWNxPLnQSbBTwhnLbkHn4hoQffKGmj78] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][pYAt90YboJpc8bfHoNc0HHcrFJyQqCDi] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][wuEi0pJnFged1l4UvPO9LcevtxIMj92m] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][PjC3abZaOHGoLMvZPr4pBKblV5IIcHhZ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][ZkPqssLzVoCpROFQHkk1W7ponrdd7eOp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][KTRPIX6n703fYGMJYSyO546NE3C6h15U] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][PWNxPLnQSbBTwhnLbkHn4hoQffKGmj78] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][jFYfpYAOetV0TVOW33f0YnXpvZNvEm99] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][WtTIk85CMtgrOFrhFPWdcyV69hjc68wg] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][KTRPIX6n703fYGMJYSyO546NE3C6h15U] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][PjC3abZaOHGoLMvZPr4pBKblV5IIcHhZ] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][jFYfpYAOetV0TVOW33f0YnXpvZNvEm99] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][kxOPFqrwhUM1Y3H7S4S7FInPxkXCXoN6] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][xc1hokRPqpNBdLHcy4TJXMtNiV8QOHlK] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][WtTIk85CMtgrOFrhFPWdcyV69hjc68wg] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][C0xPxGHxwvl5JdkqAxMqlbCvBYb5Uv3w] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][01vl4u1D6fWN03y7I7Ie1yBwxaeih8iD] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][kxOPFqrwhUM1Y3H7S4S7FInPxkXCXoN6] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][xc1hokRPqpNBdLHcy4TJXMtNiV8QOHlK] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][SDcDgQfjAK5es41QycfeMsTtxwdzZ18p] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][C0xPxGHxwvl5JdkqAxMqlbCvBYb5Uv3w] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][01vl4u1D6fWN03y7I7Ie1yBwxaeih8iD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][YkuaRgCnnrf8cxuYc2B60j681VZXdH1U] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][jRJLfLvC45y3jO2yKvdIVhbkbaZPhRKR] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][5bMNy4BBCrKo3RZlzTjL1sgkmjjIEWWa] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][SDcDgQfjAK5es41QycfeMsTtxwdzZ18p] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][YkuaRgCnnrf8cxuYc2B60j681VZXdH1U] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][OXt213iHoOFwGsMK5U1fpWrjGwVRECAH] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][jRJLfLvC45y3jO2yKvdIVhbkbaZPhRKR] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][pZJKRwWqD2uJwust86P4EPWvz1cd0V2K] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][5bMNy4BBCrKo3RZlzTjL1sgkmjjIEWWa] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][ZHWnqW1FBjSLwJnDV0lhpeqbLRaYN6V6] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][OXt213iHoOFwGsMK5U1fpWrjGwVRECAH] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][QlKgZZOKLTKrfdayqLqgqFVsMiAtz38w] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][pZJKRwWqD2uJwust86P4EPWvz1cd0V2K] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][gjMEKQeWmIDwrGdT6DlsL233tyiZUTz2] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][ZHWnqW1FBjSLwJnDV0lhpeqbLRaYN6V6] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][T5VAthZOHuwyFYSfQxR6pmGIgJytYkU6] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][QlKgZZOKLTKrfdayqLqgqFVsMiAtz38w] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][rqbxGcVAHcCx5vDNdTsLr5CTNMWOABQD] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][xejN79nWQmCOeuaC727OeuGua9J6s3vu] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][gjMEKQeWmIDwrGdT6DlsL233tyiZUTz2] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][wLqsbZv36r944YZ9IpG55HVY9y6dxF1o] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][xejN79nWQmCOeuaC727OeuGua9J6s3vu] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][T5VAthZOHuwyFYSfQxR6pmGIgJytYkU6] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][rqbxGcVAHcCx5vDNdTsLr5CTNMWOABQD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][wLqsbZv36r944YZ9IpG55HVY9y6dxF1o] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Pif7R632132tC0HOKwgwpV6Ul0pe7JNm] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][MYcOT2N73nTe6pYvRHtKfGz48HZPbwPH] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][zE1x9o8foKKDXsGJxatgtW1U2M7VAVt2] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][6FChKVHWtxGcVCWISIQwnSBtUKRCalqN] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][Pif7R632132tC0HOKwgwpV6Ul0pe7JNm] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][MYcOT2N73nTe6pYvRHtKfGz48HZPbwPH] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][zE1x9o8foKKDXsGJxatgtW1U2M7VAVt2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][72Wf1aooN5DhMU0C4qSHluaDvk4PkDs0] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][6FChKVHWtxGcVCWISIQwnSBtUKRCalqN] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][jZ9mKhgGePnLPOQf1Au7KtbuQjtK7BNJ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][0QfYxtsyDwCPPb2tI70CWb6m5ue1mGOI] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][k8wrrvjwHeUxzNaJORhlu3SgrhwQbBpZ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][72Wf1aooN5DhMU0C4qSHluaDvk4PkDs0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][0QfYxtsyDwCPPb2tI70CWb6m5ue1mGOI] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][jZ9mKhgGePnLPOQf1Au7KtbuQjtK7BNJ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][p2xYB9D9seo0D5QcisJ17cfGD1OyhKq6] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][k8wrrvjwHeUxzNaJORhlu3SgrhwQbBpZ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][gssdsekKqam0UAP5YKkBTR9xzGUYaTJp] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][N8LWAcgAYPKHwDVxJxm2Mc4d86t5xAEB] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][2Wc2LQhpSLessSTqynKJcSwBZ9kX7JAH] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][p2xYB9D9seo0D5QcisJ17cfGD1OyhKq6] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][gssdsekKqam0UAP5YKkBTR9xzGUYaTJp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][N8LWAcgAYPKHwDVxJxm2Mc4d86t5xAEB] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][UOwUtjaS73aOo3GUfSqlyETIuLB1rEEU] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][2Wc2LQhpSLessSTqynKJcSwBZ9kX7JAH] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][5naffNRYrIhxcSjwATeUY26AD5zDrGEN] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][nr5qgFnMnG9rEwmicHyS12IigM93KDaP] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][KnZmQSqHYMkx7VliSlqM8zY68widYg2x] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][UOwUtjaS73aOo3GUfSqlyETIuLB1rEEU] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][nr5qgFnMnG9rEwmicHyS12IigM93KDaP] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][5naffNRYrIhxcSjwATeUY26AD5zDrGEN] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Vs16p70IINXQ79obnKFrRkSgI62gUj0m] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][KnZmQSqHYMkx7VliSlqM8zY68widYg2x] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][ouduLfkTpJgBZ9hJrwpej3flC66dJGZD] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][CaiSzBpsr9SDYnSGWxhUbQdUuxHwddxY] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][CRoxHi8ib7xlyJAbTUz7bieH8vCA3Uqq] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][Vs16p70IINXQ79obnKFrRkSgI62gUj0m] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][ouduLfkTpJgBZ9hJrwpej3flC66dJGZD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][CaiSzBpsr9SDYnSGWxhUbQdUuxHwddxY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][5US23kxs4uZKMZ3PNPuTdRgIHWGUAhA6] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][CRoxHi8ib7xlyJAbTUz7bieH8vCA3Uqq] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][N7EyWYn5uWliqSeOio5hXed5vP1KtqBH] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][kKj90ChhrMXJwRuPhBTOxT8LN6IWTh0w] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][j9vLw3rEfdCsJVCsanAupOpezAXGqQ9I] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][5US23kxs4uZKMZ3PNPuTdRgIHWGUAhA6] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][N7EyWYn5uWliqSeOio5hXed5vP1KtqBH] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][kKj90ChhrMXJwRuPhBTOxT8LN6IWTh0w] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][j9vLw3rEfdCsJVCsanAupOpezAXGqQ9I] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][KNYZrsSm7HMsbL7I8RKayggfSch8lF4s] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][rCr3WfnPOaGhXhXROGrdQx3Pw1xg2Med] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][igB7wW8uhGBWtolRdPEezNorz4MhTS0J] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][m3W8w9PzqXd7jON1LqDZCXg5vDmxBgQe] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][KNYZrsSm7HMsbL7I8RKayggfSch8lF4s] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][m3W8w9PzqXd7jON1LqDZCXg5vDmxBgQe] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][rCr3WfnPOaGhXhXROGrdQx3Pw1xg2Med] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][igB7wW8uhGBWtolRdPEezNorz4MhTS0J] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][d3n0Y2XKEkCj656h49OYJzOxbzmTWKdh] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][iFOdsb2UjwkOgXdJF4Fm8WiaRePMgtjI] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][X83phWc8qvAB8iT4tGW8Bad7KXTYiCQr] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][j5FZCXKjKkVf7aQgThCfJ7bZt7TbRIUC] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][d3n0Y2XKEkCj656h49OYJzOxbzmTWKdh] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][iFOdsb2UjwkOgXdJF4Fm8WiaRePMgtjI] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][X83phWc8qvAB8iT4tGW8Bad7KXTYiCQr] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][j5FZCXKjKkVf7aQgThCfJ7bZt7TbRIUC] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][cp5NY2YY2DBdowbaF2WpOei8fE1NACyi] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][S7aba64U7pR6O1QjTlGb4RQPEymrHnsU] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][d8gLBzF2KiGaket1TgEXuWPX66P6lyDk] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][LF2cSyaqIZzsvfN5bA6StnMe9oLxTtIO] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][cp5NY2YY2DBdowbaF2WpOei8fE1NACyi] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][S7aba64U7pR6O1QjTlGb4RQPEymrHnsU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][d8gLBzF2KiGaket1TgEXuWPX66P6lyDk] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][LF2cSyaqIZzsvfN5bA6StnMe9oLxTtIO] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][lnaxe3rKPWiWVcqmofswnIMhpWNnR2bB] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][H2T2KasBdW1tHVZ3yVs8dkxel0G0KjYv] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][OJSQyYZECEosLKSNta1DGqJGfAUf2x6Q] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][IcL6lxGcFuxrLO4cq6adjqlcgn4NSyex] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][H2T2KasBdW1tHVZ3yVs8dkxel0G0KjYv] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][lnaxe3rKPWiWVcqmofswnIMhpWNnR2bB] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][IcL6lxGcFuxrLO4cq6adjqlcgn4NSyex] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][OJSQyYZECEosLKSNta1DGqJGfAUf2x6Q] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][aDguyFeFiTLSTyg8q5hbumBQ02LpMrAL] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][pndYuWMfPAsnE3MQ8m0Z5ncdyhijtrES] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][bQsNaB5hpbpD87PHMtJMF1cjXgM3LbQB] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][53ALY1fHJwYacaHh1aVUymQu0iwbIbo0] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][aDguyFeFiTLSTyg8q5hbumBQ02LpMrAL] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][bQsNaB5hpbpD87PHMtJMF1cjXgM3LbQB] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][pndYuWMfPAsnE3MQ8m0Z5ncdyhijtrES] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][53ALY1fHJwYacaHh1aVUymQu0iwbIbo0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][podDlC2DfxGgERVvyCR3yhW2HUUfSHOb] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Ooo8A0dA3isXK823AY7ehYpgzFEFqmJp] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][4x8Lah2LmczUkZxzo4lJviUKtTwk1NSx] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][TfxA1nH591sOplkKcV2DXIvsdPCdzqxh] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][podDlC2DfxGgERVvyCR3yhW2HUUfSHOb] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][4x8Lah2LmczUkZxzo4lJviUKtTwk1NSx] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][TfxA1nH591sOplkKcV2DXIvsdPCdzqxh] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Ooo8A0dA3isXK823AY7ehYpgzFEFqmJp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][92G3ze52zByqL8D1k5DPaz4AlR6CMgtf] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][T6UOKVv1lgzDteB2sn3FCYINmB19W7Db] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][6fsmtfwCirnsi3g686fHxqjZMDlrSopY] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][XWpDcWSQLQUIrqRzK5dcsbhi5E6UbUJg] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][6fsmtfwCirnsi3g686fHxqjZMDlrSopY] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][UjqL5IABSYtokTDkqaZ2lrZyTG3JtG0J] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][UjqL5IABSYtokTDkqaZ2lrZyTG3JtG0J] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][DZL9EnFZ42mDaVOqUwv349aIXCk95Zh0] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][DZL9EnFZ42mDaVOqUwv349aIXCk95Zh0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][UdDFKbC8nsdDDskQuSFAgh43z2HpKgGG] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][UdDFKbC8nsdDDskQuSFAgh43z2HpKgGG] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][B2PjmntTSK4t5JUfCgILuG7915bWQf78] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][B2PjmntTSK4t5JUfCgILuG7915bWQf78] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][81WmgQ1RULh8gv5HumlUe388Uuna0M5e] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][81WmgQ1RULh8gv5HumlUe388Uuna0M5e] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][mKGeh31y0iyNX1ZnWGMBLzYrBIPg6hPG] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][XWpDcWSQLQUIrqRzK5dcsbhi5E6UbUJg] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][sr6MEjBaV5kmonznL4E3qoGSr9ammaOs] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][sr6MEjBaV5kmonznL4E3qoGSr9ammaOs] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Ap4TuNSHMzHpF1kpdlVwRHxXbmxRK3Vj] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][Ap4TuNSHMzHpF1kpdlVwRHxXbmxRK3Vj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][VQZsrxeeXBt0f7kBnWHP77hVfCSVEM6m] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][VQZsrxeeXBt0f7kBnWHP77hVfCSVEM6m] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][SvtX0oH9Le1z9EBeWewUNW5Ws7IrvxYv] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][SvtX0oH9Le1z9EBeWewUNW5Ws7IrvxYv] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Pf9SdN0UEMCmzNFPjVf9VfX39SrFmxLB] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][Pf9SdN0UEMCmzNFPjVf9VfX39SrFmxLB] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][dYz9QF6KcdrwHsy9yv13NMvu7v2QhmJ7] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][92G3ze52zByqL8D1k5DPaz4AlR6CMgtf] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][bXSuLvXk90miI9cfmK06Jb61PZ5843Py] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][bXSuLvXk90miI9cfmK06Jb61PZ5843Py] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][udnqLSI728XTxIBlEebPgONZOxm2cmcb] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][udnqLSI728XTxIBlEebPgONZOxm2cmcb] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][dYOLSeM1Gpd3qsQmNUG8f2EqdUrlCOiq] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][dYOLSeM1Gpd3qsQmNUG8f2EqdUrlCOiq] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][ubLtz08HMHxUh8bp2gl7AP802cAW5CtO] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][ubLtz08HMHxUh8bp2gl7AP802cAW5CtO] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][z54mUIQ3kpFFiOJKh9WjmmG2fy4Uygwt] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][z54mUIQ3kpFFiOJKh9WjmmG2fy4Uygwt] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][GHOZaeuxPjR1URQx3pX3X2xM0hEj5ARk] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][T6UOKVv1lgzDteB2sn3FCYINmB19W7Db] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][d4lhvLLZHwM1VKS9Qf7KRruj7JyZF7U9] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][d4lhvLLZHwM1VKS9Qf7KRruj7JyZF7U9] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][r7eXkrAUBn5XugMPSgN7GVjx6aquiKJM] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][r7eXkrAUBn5XugMPSgN7GVjx6aquiKJM] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][hgEXQBQdAhU4Cp0TnaFlFMkrVR0X1yJH] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][hgEXQBQdAhU4Cp0TnaFlFMkrVR0X1yJH] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][LGFa8ewi1VnrAW61WzwgFkI1BnYZkCC5] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][LGFa8ewi1VnrAW61WzwgFkI1BnYZkCC5] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][QZBDX6RP0G024OYjj3vqF7vELhaV6cgU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][QZBDX6RP0G024OYjj3vqF7vELhaV6cgU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][8G0IStiK4FaroulrP7xqKrjzqNWxI2Hm] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][dYz9QF6KcdrwHsy9yv13NMvu7v2QhmJ7] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][GHOZaeuxPjR1URQx3pX3X2xM0hEj5ARk] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][8G0IStiK4FaroulrP7xqKrjzqNWxI2Hm] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][mKGeh31y0iyNX1ZnWGMBLzYrBIPg6hPG] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][6kQ2qV6lgDaORqO88uEq71vcuh76e3Rb] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][64Yvy1I8tTGeqx8DW6tg9hTxFV0U55yj] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][RM1nfOnUUV8TEoHB6A1CdLnXbQhba2Zp] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][6uuu9mxGhWRfmkBg3lAv4U1nBvUUpoYQ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][RM1nfOnUUV8TEoHB6A1CdLnXbQhba2Zp] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][64Yvy1I8tTGeqx8DW6tg9hTxFV0U55yj] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][6kQ2qV6lgDaORqO88uEq71vcuh76e3Rb] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][6uuu9mxGhWRfmkBg3lAv4U1nBvUUpoYQ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][sWKfOgcbTUDEtsSfauLKBzPzySHVfM3s] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][7cAhMxFMQa3SF7TvOg28vqhMCUIsVbVc] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][s9tMgKYu5ioEvX7vjiLFL1osKUGUgVhe] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][VdZo14aaZR6Aks2Zo3T2Ia3m0MXjl5Er] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][7cAhMxFMQa3SF7TvOg28vqhMCUIsVbVc] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][sWKfOgcbTUDEtsSfauLKBzPzySHVfM3s] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][s9tMgKYu5ioEvX7vjiLFL1osKUGUgVhe] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][2hk0OB9Cy7hYGop6ab4yj3S0bJuSlYqM] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][VdZo14aaZR6Aks2Zo3T2Ia3m0MXjl5Er] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][YYXtXmz9x9xYvFNhuJ6xMSWVT5qkMX1l] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][rDR0pDC5HAnJimIAIlqsps4R0XVG8At4] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][tMICXfDUQMu2v00jK6LOZwjl6bfGWglA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][2hk0OB9Cy7hYGop6ab4yj3S0bJuSlYqM] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][YYXtXmz9x9xYvFNhuJ6xMSWVT5qkMX1l] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Fyipa3oHEQkbAcPTv2JNpLgm2TE43wPm] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][rDR0pDC5HAnJimIAIlqsps4R0XVG8At4] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][tMICXfDUQMu2v00jK6LOZwjl6bfGWglA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][2nuJ8TuKIAvBHGy4G4IgDIkCUd87Dn87] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][9d9Zk1Avx6dnnnsTJ6WbbuJYPqgL2eBm] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][mgWDVuQMrZOnmw8LvSmmAwbBCIvrW6O5] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][Fyipa3oHEQkbAcPTv2JNpLgm2TE43wPm] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][2nuJ8TuKIAvBHGy4G4IgDIkCUd87Dn87] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][xRhcwk3yZ88x9GctPP2BBBHJlLQfp3Wg] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][mgWDVuQMrZOnmw8LvSmmAwbBCIvrW6O5] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][9d9Zk1Avx6dnnnsTJ6WbbuJYPqgL2eBm] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][kwqqvZ28RDu3yggJgTlYR1MdWN1hmtYN] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][270SF9w3SA3Jo5bZbTAKqkXtvC22RLqA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][T8t3KcCMtzHoY23wJJiX5Q3CGlSB6Yy3] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][xRhcwk3yZ88x9GctPP2BBBHJlLQfp3Wg] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][kwqqvZ28RDu3yggJgTlYR1MdWN1hmtYN] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][T8t3KcCMtzHoY23wJJiX5Q3CGlSB6Yy3] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][270SF9w3SA3Jo5bZbTAKqkXtvC22RLqA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][lfYRcK0mFT5HP8Iu5SpR41lrPMcGd6Yk] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Nv1FznEoZVBA8nno095OymUyMhKe25gL] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][47rBKsp9MKNb0aTexBkicgQzcaGKHYSv] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][qloxZZEty5BhoDLpoylSkmL0rHeYQSJV] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][lfYRcK0mFT5HP8Iu5SpR41lrPMcGd6Yk] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][Nv1FznEoZVBA8nno095OymUyMhKe25gL] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][47rBKsp9MKNb0aTexBkicgQzcaGKHYSv] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][h1JJ31IIOG64ugCREwer8EQvdaPNj7Sl] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][qloxZZEty5BhoDLpoylSkmL0rHeYQSJV] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Z8It1h0UuvdO4hOtpGSFyvcPxObjvD87] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][CYFO1nreeKA1oSbGkFwN301ko8rtenqz] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][8Z1n0E7zCad5eVnqSJvt23kFcdET0QFt] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][h1JJ31IIOG64ugCREwer8EQvdaPNj7Sl] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][CYFO1nreeKA1oSbGkFwN301ko8rtenqz] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Z8It1h0UuvdO4hOtpGSFyvcPxObjvD87] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][RCWwfPDOgUel1V7FOBH098jA6A8YBS6Z] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][8Z1n0E7zCad5eVnqSJvt23kFcdET0QFt] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][6n8oGWxwIZ8gVwAptapbn38eqptIOA5E] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][oRDkxx311neKjNEoaDIWqnHP4e0MlNpI] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][zi2v0KrOx0wrG0SYzZ5ObPnZYwkNxgQE] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][6n8oGWxwIZ8gVwAptapbn38eqptIOA5E] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][oRDkxx311neKjNEoaDIWqnHP4e0MlNpI] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][RCWwfPDOgUel1V7FOBH098jA6A8YBS6Z] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][ILnPzPkcrNRsuJfASunN7lnEJJowZNzx] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Ltx5ZoTP0PAbkuM80nnFhsBRvVUjuicH] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Ixjt7FmgoDXHbc6I3yEyvycKpobfIfxO] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][zi2v0KrOx0wrG0SYzZ5ObPnZYwkNxgQE] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][iRUhrXhGGupNC4wmL1yY3RMyEiAMauhe] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][ILnPzPkcrNRsuJfASunN7lnEJJowZNzx] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Ixjt7FmgoDXHbc6I3yEyvycKpobfIfxO] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][Ltx5ZoTP0PAbkuM80nnFhsBRvVUjuicH] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][duRWD8PyyUb6DvmU6mkaFXML5BIB4ncS] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][gdqMjBrCMviPUpHBfGMTCHbuCHONWVzg] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][iRUhrXhGGupNC4wmL1yY3RMyEiAMauhe] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][1jZCGOKyn0CXWBqYgP1B5SakpBwY4mnn] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][NXARszE0vSzlNNJ48QsZsQ2anjZBqvte] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][duRWD8PyyUb6DvmU6mkaFXML5BIB4ncS] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][gdqMjBrCMviPUpHBfGMTCHbuCHONWVzg] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][1jZCGOKyn0CXWBqYgP1B5SakpBwY4mnn] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][CGduYlBYGOj9aNqwwUE4LM1cqeZGTEQA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][NXARszE0vSzlNNJ48QsZsQ2anjZBqvte] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][ad9ZQtfUeQcNpgz0vxuj9HlveXVpXEWS] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][60ouF3u3Uvc3srnFDIIqC8iyR3F3BqUG] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][wAYjYoUo5OSqZ9e2uwDYkuCUkJyo6mTD] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][CGduYlBYGOj9aNqwwUE4LM1cqeZGTEQA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][ad9ZQtfUeQcNpgz0vxuj9HlveXVpXEWS] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][fVUM3yX7ZW5ptHlWc2cLDiP640GL6hhe] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][JiNOrckN9KTrsFE201gcHnwK1ACHsmFz] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][wAYjYoUo5OSqZ9e2uwDYkuCUkJyo6mTD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][60ouF3u3Uvc3srnFDIIqC8iyR3F3BqUG] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][9dKSbqhVzDQQQVpsYa360nDrZlCCDApP] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][T5Q0oUMnBGBgxhkUUY0pteyAszwyFahn] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][JiNOrckN9KTrsFE201gcHnwK1ACHsmFz] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][fVUM3yX7ZW5ptHlWc2cLDiP640GL6hhe] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][nGI9IyieQrwRthlOhr79BUnn6J0aFiih] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][9dKSbqhVzDQQQVpsYa360nDrZlCCDApP] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][7PBvyLhRcAM9rBhulVHTF6cqudsxk8ce] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][T5Q0oUMnBGBgxhkUUY0pteyAszwyFahn] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][Ayy05zv3dZQ9HtuJGhTIyywV7QCsQbqs] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][7PBvyLhRcAM9rBhulVHTF6cqudsxk8ce] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][70YaiPh3eDqXb2Xul6HweYnBxXrSEJCO] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][nGI9IyieQrwRthlOhr79BUnn6J0aFiih] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][xbV6qaRu85iJJgC5wNqCpMBjHpTZ1dSf] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][Ayy05zv3dZQ9HtuJGhTIyywV7QCsQbqs] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][70YaiPh3eDqXb2Xul6HweYnBxXrSEJCO] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][fubqBQ2Vy8Q4Q7Xl62wRAzWU9YnZjSwf] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][xbV6qaRu85iJJgC5wNqCpMBjHpTZ1dSf] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][v0dORALoIOPtjLxizMwWjSyXQZBDfIZE] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][MJsDPQAtkkyFkbmvBv6GvQtTsRlbAUsM] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][fubqBQ2Vy8Q4Q7Xl62wRAzWU9YnZjSwf] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][vxpWSlmUiw2XHCDv3HADhaZc1LjVF42b] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][22qFb9SkoiEkpzBsR8KycogMm8xTGriJ] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][MJsDPQAtkkyFkbmvBv6GvQtTsRlbAUsM] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][v0dORALoIOPtjLxizMwWjSyXQZBDfIZE] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][IeVfqlIf8zIjDLbhbs7xyKX7aSaQlAWT] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][vxpWSlmUiw2XHCDv3HADhaZc1LjVF42b] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][0s3LZNOq4svmJvAtl8qNJXGoJCkiBVHO] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][22qFb9SkoiEkpzBsR8KycogMm8xTGriJ] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][qk3p2QXUlJ9HOLvMv2yinDb8rkkeCxbB] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][IeVfqlIf8zIjDLbhbs7xyKX7aSaQlAWT] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][XnYu2h8UlxifDgofBg1P1YXuOi70E2IC] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][oqSMpnn4URhS74E1VxdAez86ekwD8Dc7] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][0s3LZNOq4svmJvAtl8qNJXGoJCkiBVHO] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][qk3p2QXUlJ9HOLvMv2yinDb8rkkeCxbB] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][XnYu2h8UlxifDgofBg1P1YXuOi70E2IC] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][mBrFsFZA4rAEeaiIF4v5jwXSgWwjYErm] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][aZdS34uYefCpqJC1qG6MX72kyKHklEx9] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][oqSMpnn4URhS74E1VxdAez86ekwD8Dc7] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][4LYNhuRmxVygmPadwD5ftVZOJBbvZDtn] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][mBrFsFZA4rAEeaiIF4v5jwXSgWwjYErm] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][BOOnu0jn6ST8zCRaHUzqBeKb0TzdRD4A] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][4LYNhuRmxVygmPadwD5ftVZOJBbvZDtn] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][aZdS34uYefCpqJC1qG6MX72kyKHklEx9] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][lnubEDvvIutB579LSLJGj6VQ14w01ncC] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][BOOnu0jn6ST8zCRaHUzqBeKb0TzdRD4A] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][hzcIHD2Vi61RQbyvn5eff667bHzozHmW] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][31g6vDLCXB17H7mrAmsCwwdUFbrIagPC] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][lnubEDvvIutB579LSLJGj6VQ14w01ncC] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][pN9TZ4fqueXHbGWtscABCFQEBFTcOUnO] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][hzcIHD2Vi61RQbyvn5eff667bHzozHmW] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][31g6vDLCXB17H7mrAmsCwwdUFbrIagPC] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][2Ej9t0eFdJewrMjZVK6Y516SJ08WCnwN] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][pN9TZ4fqueXHbGWtscABCFQEBFTcOUnO] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][ylLM0hs5A9Pxd5l4rQJQh2Ky3luWmOzA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][JvHswvZmRkX9Ox8m3k1gSmM9CF77CwCj] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][bmv5sqiu9ifuRoqskX3btVZG2Os7Wgep] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][2Ej9t0eFdJewrMjZVK6Y516SJ08WCnwN] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][ylLM0hs5A9Pxd5l4rQJQh2Ky3luWmOzA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][JvHswvZmRkX9Ox8m3k1gSmM9CF77CwCj] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][bmv5sqiu9ifuRoqskX3btVZG2Os7Wgep] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][h2noXTxBykrqKruLqcNgNseAK4IT2QIC] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][aM8DxZlDi1Ii0jd1Fove0gfKJkQaBYXa] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][9EhGcnjYE9o4zsA4c5ctZJqoBYBiYSIv] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][J8BO4HMDpbAWijhXYE4NxPPZeKXEwg7E] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][h2noXTxBykrqKruLqcNgNseAK4IT2QIC] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][aM8DxZlDi1Ii0jd1Fove0gfKJkQaBYXa] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][9EhGcnjYE9o4zsA4c5ctZJqoBYBiYSIv] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][J8BO4HMDpbAWijhXYE4NxPPZeKXEwg7E] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][0vMLrTdqLKnMAnQfAA8pfsIiM7MemvFn] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][AemGAr6aGxtdUGUHAXcDovqohHXCrFLA] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][kzZWNIcfHcUMjhxoAG1cmaVnRbtJszow] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][BvjkIfhuhMR6OQ5UD1E2zmL650ExtFNJ] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][0vMLrTdqLKnMAnQfAA8pfsIiM7MemvFn] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][AemGAr6aGxtdUGUHAXcDovqohHXCrFLA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][BvjkIfhuhMR6OQ5UD1E2zmL650ExtFNJ] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][kzZWNIcfHcUMjhxoAG1cmaVnRbtJszow] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][MX2dx5TEqrF4wbdtEBiSU55LzGmXxThr] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][lYNhcXs3Kwdgwh9L2KmZA9EWq2fSov0e] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][yMBW0TAEdr6dLgv1BkVFfNqrEaaaDaJt] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][SHtOPmZbuZ99Lxq0GjZjUNCW4kibcTRc] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][MX2dx5TEqrF4wbdtEBiSU55LzGmXxThr] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][yMBW0TAEdr6dLgv1BkVFfNqrEaaaDaJt] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][lYNhcXs3Kwdgwh9L2KmZA9EWq2fSov0e] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][SHtOPmZbuZ99Lxq0GjZjUNCW4kibcTRc] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][BPibkwD23lkYbbhdbPQh7mJypywc0IL3] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Xn3ZOy4h71diOnwoyuI5l0SDbWLsfdF5] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][l3wg1vfGmUDonhq57FIBHr6SZZTLX4Z2] Processing: App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][jgRlN9KuNph9uvmGjV0ZfMkHUhZjlHB4] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][BPibkwD23lkYbbhdbPQh7mJypywc0IL3] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][Xn3ZOy4h71diOnwoyuI5l0SDbWLsfdF5] Processed:  App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][l3wg1vfGmUDonhq57FIBHr6SZZTLX4Z2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][jgRlN9KuNph9uvmGjV0ZfMkHUhZjlHB4] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][ZmU03c9wBGyMeEZlWR1pdVyXvY5DrNZm] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][ZmU03c9wBGyMeEZlWR1pdVyXvY5DrNZm] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][PPvmnSE2hZPKFYVpI8m07ayP8cqsJCeD] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][PPvmnSE2hZPKFYVpI8m07ayP8cqsJCeD] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][KF1O3uwWS4pvrvpnaFW9R8wMJ6YvVlJH] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][KF1O3uwWS4pvrvpnaFW9R8wMJ6YvVlJH] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][xLnU159tdU10eO9CfJBWsJBAHuhPtCor] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][xLnU159tdU10eO9CfJBWsJBAHuhPtCor] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][MoisL2IgB9g3Zx6MR6ZZLwHLnrzQJk3b] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][MoisL2IgB9g3Zx6MR6ZZLwHLnrzQJk3b] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][LRyAypR7zTJsWPFr79Fp2TxgS20lH6k0] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][LRyAypR7zTJsWPFr79Fp2TxgS20lH6k0] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][495y6C5oVoGDppqTlbuhxwcJ9LmK9Nq2] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][495y6C5oVoGDppqTlbuhxwcJ9LmK9Nq2] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][Siq7W5PvTwG6RZ8Wq58cNJDiTshiIQ4a] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][Siq7W5PvTwG6RZ8Wq58cNJDiTshiIQ4a] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][4O5r3XgFkxGIXB618iKW037xXNA8KE73] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][4O5r3XgFkxGIXB618iKW037xXNA8KE73] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][FvYMe1GSKVoip6AIfXrgNiQ5jE8SUsxU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][FvYMe1GSKVoip6AIfXrgNiQ5jE8SUsxU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][eFmmivDYB9IFO75G1jdPkgkn5ZrTxF4o] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][eFmmivDYB9IFO75G1jdPkgkn5ZrTxF4o] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][maAJqtHTXmkypD6z3nVZtKsm0F9NEEkT] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][maAJqtHTXmkypD6z3nVZtKsm0F9NEEkT] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][a2zSKSf9HKTLrjpYLmNRdGph9hd1a5jU] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][a2zSKSf9HKTLrjpYLmNRdGph9hd1a5jU] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][mX3SZdVcb5Q2zWS1xhKudnE02ISUjaqw] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][mX3SZdVcb5Q2zWS1xhKudnE02ISUjaqw] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][FGdcZkjlZl9fEZAcRhxvcf3zp49UP6zA] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][FGdcZkjlZl9fEZAcRhxvcf3zp49UP6zA] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][4bpZzdJjgGNSQiQdG6usRGp8kTgOrTdB] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
[2021-06-11 12:20:33][4bpZzdJjgGNSQiQdG6usRGp8kTgOrTdB] Processed:  App\Jobs\ReadExpectWrite
[2021-06-11 12:20:33][6br3UVZaPO3ljp1aQoehP7VbKRA9pqxH] Processing: App\Jobs\ReadExpectWrite
"SUCCESS!!! write PDO after past write action"
```
