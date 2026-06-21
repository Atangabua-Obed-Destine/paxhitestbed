<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Book;

class BooksExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Book::get(['title', 'isbn', 'author', 'publisher', 'edition', 'publish_year', 'language', 'price', 'quantity']);
    }

    public function headings(): array
    {
        return ['title', 'isbn', 'author', 'publisher', 'edition', 'publish_year', 'language', 'price', 'quantity'];
    }
}
