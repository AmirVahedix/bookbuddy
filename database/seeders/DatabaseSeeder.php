<?php

namespace Database\Seeders;

use App\Enums\BookFileType;
use App\Models\Book;
use App\Models\BookSection;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Setup a default User
        User::factory()->create([
            'name' => 'Default User',
            'email' => 'test@example.com',
        ]);

        // 2. Attach a couple of sample books
        // PDF Book
        $pdfBook = Book::factory()->create([
            'title' => 'Designing Data-Intensive Applications',
            'author' => 'Martin Kleppmann',
            'file_type' => BookFileType::Pdf,
            'file_path' => 'books/designing-data-intensive-applications.pdf',
            'total_pages' => 611,
            'current_page' => 45,
        ]);

        // EPUB Book
        $epubBook = Book::factory()->create([
            'title' => 'Structure and Interpretation of Computer Programs',
            'author' => 'Harold Abelson, Gerald Jay Sussman, Julie Sussman',
            'file_type' => BookFileType::Epub,
            'file_path' => 'books/sicp.epub',
            'total_pages' => 657,
            'current_page' => 110,
        ]);

        // 3. Map structural sections for the EPUB book
        $section1 = BookSection::factory()->create([
            'book_id' => $epubBook->id,
            'title' => 'Chapter 1: Building Abstractions with Procedures',
            'section_identifier' => 'OEBPS/xhtml/ch1.xhtml',
            'start_page' => 1,
            'end_page' => 94,
            'order' => 1,
        ]);

        $section2 = BookSection::factory()->create([
            'book_id' => $epubBook->id,
            'title' => 'Chapter 2: Building Abstractions with Data',
            'section_identifier' => 'OEBPS/xhtml/ch2.xhtml',
            'start_page' => 95,
            'end_page' => 216,
            'order' => 2,
        ]);

        $section3 = BookSection::factory()->create([
            'book_id' => $epubBook->id,
            'title' => 'Chapter 3: Modularity, Objects, and State',
            'section_identifier' => 'OEBPS/xhtml/ch3.xhtml',
            'start_page' => 217,
            'end_page' => 388,
            'order' => 3,
        ]);

        // 4. Provision sample markdown summaries

        // Summaries for the PDF Book (Precise page processing)
        Summary::factory()->create([
            'book_id' => $pdfBook->id,
            'book_section_id' => null,
            'target_pages' => [1, 2, 3, 4, 5],
            'prompt_used' => 'Summarize Chapter 1 introduction on reliable, scalable, and maintainable applications.',
            'generated_summary' => <<<'MARKDOWN'
# Chapter 1: Reliable, Scalable, and Maintainable Applications

This chapter introduces the fundamental concepts of data-intensive applications.

## 1. Reliability
- Systems must function correctly under adversity (hardware/software faults, human errors).
- **Fault-tolerance**: Anticipating and coping with faults rather than preventing them entirely.

## 2. Scalability
- The ability to handle increased load.
- Uses **load parameters** (e.g., QPS, read-to-write ratio) to describe system stress.

## 3. Maintainability
- Making systems easy to understand, change, and operate.
- Three key design principles: **Operability**, **Simplicity**, and **Evolvability**.
MARKDOWN,
            'tokens_used' => 1250,
        ]);

        Summary::factory()->create([
            'book_id' => $pdfBook->id,
            'book_section_id' => null,
            'target_pages' => [39, 40, 41, 42],
            'prompt_used' => 'Analyze storage engines: LSM-Trees vs B-Trees as explained in these pages.',
            'generated_summary' => <<<'MARKDOWN'
# Storage Engines: LSM-Trees vs B-Trees

A comparison of storage engine architectures under different workloads.

## Log-Structured Merge-Trees (LSM-Trees)
- **Concept**: Append-only log file structured with Sorted String Tables (SSTables).
- **Writes**: Extremely fast. Writes are directed to an in-memory memtable, then flushed to disk as SSTables.
- **Reads**: Slow. Must check memtable and multiple SSTable segments.
- **Compaction**: Periodic merging of SSTables in the background to remove duplicates.

## B-Trees
- **Concept**: Breaks database down into fixed-size pages (usually 4KB), referencing child pages in a tree structure.
- **Writes**: Slower. Requires overwriting pages in-place, which can cause page splits.
- **Reads**: Extremely fast. Locates key by traversing a bounded tree structure.
MARKDOWN,
            'tokens_used' => 1480,
        ]);

        // Summaries for the EPUB Book (Section-based processing)
        Summary::factory()->create([
            'book_id' => $epubBook->id,
            'book_section_id' => $section1->id,
            'target_pages' => null,
            'prompt_used' => 'Summarize the core concepts of Building Abstractions with Procedures in Chapter 1.',
            'generated_summary' => <<<'MARKDOWN'
# Chapter 1 Summary: Building Abstractions with Procedures

This chapter introduces procedural abstraction using Scheme (Lisp).

## Key Themes
- **Elements of Programming**: Primitive expressions, combinations, and abstractions.
- **Substitution Model**: Evaluating combinations by replacing parameters with arguments (Applicative vs Normal order).
- **Procedures as Black-Box Abstractions**: Separating how a procedure is implemented from how it is used.
- **Recursion vs Iteration**:
  - *Recursive processes* grow and shrink as deferred operations build up.
  - *Iterative processes* run in constant space using state variables (tail-recursive).
MARKDOWN,
            'tokens_used' => 1800,
        ]);

        Summary::factory()->create([
            'book_id' => $epubBook->id,
            'book_section_id' => $section2->id,
            'target_pages' => null,
            'prompt_used' => 'Explain hierarchical data structures and the closure property in Chapter 2.',
            'generated_summary' => <<<'MARKDOWN'
# Chapter 2 Summary: Building Abstractions with Data

This chapter explores data abstraction and compound data structures.

## Core Concepts
- **Data Abstraction**: Isolating the representation of data from its use.
- **Glue & Pairs**: Constructing pairs with `cons`, and accessing elements with `car` and `cdr`.
- **Closure Property**: The ability to combine elements to create compound data that can themselves be combined. This allows building hierarchical structures like lists and trees.
- **Symbolic Data**: Working with symbols directly to represent algebraic expressions, sets, or graphs.
MARKDOWN,
            'tokens_used' => 1950,
        ]);
    }
}
