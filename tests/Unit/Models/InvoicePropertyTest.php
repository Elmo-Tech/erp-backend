<?php

namespace Tests\Unit\Models;

use App\Models\Invoice;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for Invoice Model
 * 
 * Feature: client-portal-security, Property 10: Invoice Number Generation
 * Validates: Requirements 17.1, 17.2, 17.3, 17.4, 17.5
 */
class InvoicePropertyTest extends TestCase
{
    use TestTrait, RefreshDatabase;

    /**
     * Property 10: Invoice Number Generation
     * 
     * For any generated invoice number, it SHALL match the format INV-YYYY-NNNNNN.
     * For any two invoices, their invoice numbers SHALL be unique.
     * For any invoice created in a new year, the sequence SHALL reset to 000001.
     * 
     * **Validates: Requirements 17.1, 17.2, 17.3, 17.4, 17.5**
     */
    public function test_invoice_number_generation_format_and_uniqueness(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 50) // Number of invoices to generate
            )
            ->then(function ($invoiceCount) {
                $generatedNumbers = [];
                $currentYear = now()->year;
                
                for ($i = 0; $i < $invoiceCount; $i++) {
                    $invoiceNumber = Invoice::generateInvoiceNumber();
                    
                    // Test format: INV-YYYY-NNNNNN
                    $this->assertMatchesRegularExpression(
                        '/^INV-\d{4}-\d{6}$/',
                        $invoiceNumber,
                        "Invoice number '{$invoiceNumber}' does not match format INV-YYYY-NNNNNN"
                    );
                    
                    // Test year matches current year
                    $yearFromNumber = (int) substr($invoiceNumber, 4, 4);
                    $this->assertEquals(
                        $currentYear,
                        $yearFromNumber,
                        "Invoice number year '{$yearFromNumber}' does not match current year '{$currentYear}'"
                    );
                    
                    // Test uniqueness
                    $this->assertNotContains(
                        $invoiceNumber,
                        $generatedNumbers,
                        "Invoice number '{$invoiceNumber}' is not unique"
                    );
                    
                    $generatedNumbers[] = $invoiceNumber;
                    
                    // Create an actual invoice to simulate database state
                    Invoice::factory()->create([
                        'invoice_number' => $invoiceNumber,
                    ]);
                }
                
                // Test sequence increments properly
                if ($invoiceCount > 1) {
                    $firstSequence = (int) substr($generatedNumbers[0], -6);
                    $lastSequence = (int) substr($generatedNumbers[$invoiceCount - 1], -6);
                    
                    $this->assertEquals(
                        $invoiceCount,
                        $lastSequence - $firstSequence + 1,
                        "Invoice sequence does not increment properly"
                    );
                }
            });
    }

    /**
     * Property 10: Invoice Number Generation - Sequence Reset for New Year
     * 
     * For any invoice created in a new year (when no invoices exist for that year),
     * the sequence SHALL start at 000001.
     * 
     * **Validates: Requirements 17.3**
     */
    public function test_invoice_number_sequence_resets_for_new_year(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(2020, 2030) // Test different years
            )
            ->then(function ($testYear) {
                // Clear any existing invoices
                Invoice::query()->delete();
                
                // Mock the current year
                $this->travelTo(now()->setYear($testYear)->startOfYear());
                
                $firstInvoiceNumber = Invoice::generateInvoiceNumber();
                
                // Should start with 000001 for a new year
                $expectedNumber = "INV-{$testYear}-000001";
                $this->assertEquals(
                    $expectedNumber,
                    $firstInvoiceNumber,
                    "First invoice of year {$testYear} should be {$expectedNumber}, got {$firstInvoiceNumber}"
                );
                
                // Create the invoice and generate another
                Invoice::factory()->create([
                    'invoice_number' => $firstInvoiceNumber,
                ]);
                
                $secondInvoiceNumber = Invoice::generateInvoiceNumber();
                $expectedSecondNumber = "INV-{$testYear}-000002";
                
                $this->assertEquals(
                    $expectedSecondNumber,
                    $secondInvoiceNumber,
                    "Second invoice of year {$testYear} should be {$expectedSecondNumber}, got {$secondInvoiceNumber}"
                );
            });
    }

    /**
     * Property 10: Invoice Number Generation - Sequence Continuation
     * 
     * For any existing invoices in the current year, new invoice numbers
     * SHALL continue the sequence from the highest existing number.
     * 
     * **Validates: Requirements 17.2, 17.4**
     */
    public function test_invoice_number_continues_existing_sequence(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(1, 999999) // Starting sequence number
            )
            ->then(function ($startingSequence) {
                // Clear any existing invoices
                Invoice::query()->delete();
                
                $currentYear = now()->year;
                $existingInvoiceNumber = sprintf('INV-%d-%06d', $currentYear, $startingSequence);
                
                // Create an existing invoice with a specific sequence
                Invoice::factory()->create([
                    'invoice_number' => $existingInvoiceNumber,
                ]);
                
                // Generate a new invoice number
                $newInvoiceNumber = Invoice::generateInvoiceNumber();
                $expectedNewNumber = sprintf('INV-%d-%06d', $currentYear, $startingSequence + 1);
                
                $this->assertEquals(
                    $expectedNewNumber,
                    $newInvoiceNumber,
                    "New invoice number should continue sequence from {$startingSequence} to " . ($startingSequence + 1)
                );
            });
    }
}