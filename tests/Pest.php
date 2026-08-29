<?php

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/*
| DatabaseTruncation, not RefreshDatabase.
|
| RefreshDatabase wraps each test in a transaction and rolls it back. InnoDB
| does not add uncommitted rows to a FULLTEXT index, so under that trait every
| row a test creates is invisible to MATCH() - tier C of the search would
| silently look broken while working perfectly in production.
|
| Truncating between tests keeps writes committed, at the cost of some speed.
*/
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Feature', 'Unit');
