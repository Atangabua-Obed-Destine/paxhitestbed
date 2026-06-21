# Financial Statements Quick Reference Guide

## 🚀 Quick Access

### Balance Sheet (Bilan)
**URL:** `/admin/general-ledger/balance-sheet`  
**Shows:** Financial position at a specific date  
**Reports:** Assets, Liabilities, Equity

### Income Statement (Compte de Résultat)
**URL:** `/admin/general-ledger/income-statement`  
**Shows:** Financial performance over a period  
**Reports:** Revenue, Expenses, Profit/Loss

---

## 📊 Balance Sheet - Quick Guide

### What It Shows
| Section | OHADA Class | Description |
|---------|-------------|-------------|
| **Fixed Assets** | Class 2 | Buildings, equipment, furniture |
| **Inventory** | Class 3 | Stock items, raw materials |
| **Receivables** | Class 4 (Debit) | Money owed TO you |
| **Cash & Banks** | Class 5 | Cash, bank accounts |
| **Equity** | Class 1 | Capital, retained earnings |
| **Payables** | Class 4 (Credit) | Money you owe TO others |

### How to Use
1. Select "As of Date" (e.g., December 31, 2025)
2. Optional: Select "Comparative Date" (e.g., December 31, 2024)
3. Click "Apply Filter"
4. Review the report
5. Export or Print

### Key Check
✅ **Total Assets MUST EQUAL Total Liabilities & Equity**  
If not balanced, investigate:
- Unposted journal entries
- Incorrect account setup
- Data entry errors

---

## 💰 Income Statement - Quick Guide

### What It Shows
| Section | OHADA Class | Description |
|---------|-------------|-------------|
| **Revenue** | Class 7 | Income from all sources |
| **Expenses** | Class 6 | All costs and expenses |
| **Other Results** | Class 8 | Exceptional items |
| **Net Profit/Loss** | Calculated | Final result |

### How to Use
1. Select "Start Date" (optional - leave blank for inception)
2. Select "End Date" (e.g., December 31, 2025)
3. Click "Apply Filter"
4. Review by category
5. Check summary cards for quick metrics
6. Export or Print

### Key Metrics
- **Total Revenue:** All income
- **Total Expenses:** All costs
- **Net Profit/Loss:** Revenue - Expenses + Other Results
- **Profit Margin:** (Net Profit / Revenue) × 100

---

## 🎯 Common Tasks

### Monthly Financial Close
```
1. Post all journal entries for the month
2. Run Balance Sheet as of month-end
3. Verify it's balanced
4. Run Income Statement for the month
5. Review profit/loss
6. Export both reports to PDF
7. Archive for records
```

### Yearly Financial Close
```
1. Complete monthly close for December
2. Run Balance Sheet as of December 31
3. Run Income Statement for full year (Jan 1 - Dec 31)
4. Compare with previous year (use comparative date)
5. Calculate annual profit/loss
6. Prepare for audit
7. Transfer net result to retained earnings
```

### Quarterly Review
```
1. Run Balance Sheet at quarter end
2. Run Income Statement for quarter
3. Compare with previous quarter
4. Analyze trends
5. Export for stakeholders
```

---

## 🖨️ Export Options

### Print (Browser)
- Click **Print** button
- Or press `Ctrl + P` (Windows) / `Cmd + P` (Mac)
- Select printer or "Save as PDF"
- Adjust settings if needed
- Print/Save

### Export to PDF
- Click **Export PDF** button
- File downloads automatically
- Filename format: `balance-sheet-2025-12-31.pdf`
- Opens in new tab (can save from there)

### Export to Excel
- Click **Export Excel** button
- Spreadsheet downloads
- Can edit and analyze further
- Good for creating charts

---

## 🔍 Troubleshooting Quick Fixes

### Balance Sheet Not Balanced
```sql
-- Check unposted entries
SELECT * FROM journal_entries WHERE is_posted = 0;

-- Post them or delete if test entries
UPDATE journal_entries SET is_posted = 1 WHERE id = X;
```

### No Data Showing
```sql
-- Check if you have posted entries
SELECT COUNT(*) FROM journal_entries WHERE is_posted = 1;

-- Check date range
SELECT MIN(entry_date), MAX(entry_date) FROM journal_entries;
```

### Wrong Balances
```bash
# Run recalculation script
php recalculate_balances.php
```

---

## 📱 Navigation

### From Dashboard
```
Dashboard → Accounting → General Ledger → [Balance Sheet or Income Statement]
```

### From Sidebar
```
Sidebar → Accounting → General Ledger → General Ledger Dashboard
Then click on the desired report card
```

---

## ⚡ Keyboard Shortcuts

| Action | Shortcut |
|--------|----------|
| Print | `Ctrl + P` / `Cmd + P` |
| Go Back | `Alt + ←` / `Cmd + [` |
| Refresh | `F5` / `Cmd + R` |

---

## 🎨 Visual Indicators

### Balance Sheet
- 🟢 **Green Alert:** Balanced (Assets = Liabilities)
- 🔴 **Red Alert:** Not Balanced (check your data)
- 🔵 **Blue Headers:** Section titles
- 🟡 **Yellow Rows:** Category subtotals

### Income Statement
- 🟢 **Green Sections:** Revenue (income)
- 🔴 **Red Sections:** Expenses (costs)
- 🔵 **Blue Box:** Net Profit (if positive)
- 🟠 **Orange Box:** Net Loss (if negative)

---

## 📞 Need Help?

### Common Questions

**Q: When should I run these reports?**  
A: At least monthly, and always before period close.

**Q: Can I run for any date range?**  
A: Yes! Balance Sheet is point-in-time, Income Statement is period-based.

**Q: What if my Balance Sheet doesn't balance?**  
A: Check for unposted entries, run recalculate script, verify account setup.

**Q: Can I compare years?**  
A: Yes! Use the comparative date feature on Balance Sheet.

**Q: How do I calculate profit margin?**  
A: It's automatic! Shown in the summary card: (Net Profit ÷ Revenue) × 100

---

## 📋 Checklist Before Running Reports

### Balance Sheet Checklist
- [ ] All journal entries posted
- [ ] Date selected (as of date)
- [ ] Comparative date (if needed)
- [ ] Accounts properly classified
- [ ] Opening balances verified

### Income Statement Checklist
- [ ] All transactions for period posted
- [ ] Start date selected (optional)
- [ ] End date selected (required)
- [ ] Period covers desired timeframe
- [ ] Revenue and expense accounts active

---

## 🎓 Tips for Best Results

1. **Post First, Report Later**
   - Always post journal entries before running reports
   - Draft entries won't appear

2. **Use Comparative Dates**
   - Compare current vs previous month
   - Or current year vs last year
   - Helps identify trends

3. **Regular Schedule**
   - Monthly: Balance Sheet + Income Statement
   - Quarterly: Full analysis with comparisons
   - Annually: Year-end statements for audit

4. **Export Everything**
   - Keep PDF copies for records
   - Export Excel for further analysis
   - Archive by period

5. **Verify Balance**
   - Balance Sheet should ALWAYS balance
   - If not, investigate immediately
   - Don't close period with unbalanced sheet

---

## 📚 Related Documentation

- **Full Guide:** `FINANCIAL_STATEMENTS_DOCUMENTATION.md`
- **Journal Entries:** `JOURNAL_ENTRY_VALIDATION_README.md`
- **Trash Management:** `JOURNAL_ENTRY_TRASH_DOCUMENTATION.md`

---

**Quick Reference Version 1.0**  
**Created:** October 10, 2025  
**Last Updated:** October 10, 2025
