<?php

use App\Services\QueryNormalizer;

beforeEach(fn () => $this->normalizer = new QueryNormalizer);

it('lowercases and trims', function () {
    expect($this->normalizer->normalize('  BeRiTa  '))->toBe('berita');
});

it('collapses internal whitespace', function () {
    expect($this->normalizer->normalize("toko\t\n  pedia"))->toBe('toko pedia');
});

it('strips punctuation people type by accident', function () {
    expect($this->normalizer->normalize('tokopedia!!!???'))->toBe('tokopedia');
});

it('keeps hyphens inside words', function () {
    expect($this->normalizer->normalize('e-commerce'))->toBe('e-commerce');
});

it('handles null and empty input', function () {
    expect($this->normalizer->normalize(null))->toBe('')
        ->and($this->normalizer->normalize('   '))->toBe('');
});

it('preserves non-ascii letters', function () {
    expect($this->normalizer->normalize('Ekonomi Indonésia'))->toBe('ekonomi indonésia');
});

it('caps length to what the keyword column can hold', function () {
    expect(mb_strlen($this->normalizer->normalize(str_repeat('a', 400))))->toBe(255);
});

it('splits into terms', function () {
    expect($this->normalizer->terms('Berita  Terkini!'))->toBe(['berita', 'terkini'])
        ->and($this->normalizer->terms(''))->toBe([]);
});
