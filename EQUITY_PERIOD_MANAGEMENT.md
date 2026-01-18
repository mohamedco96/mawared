# Equity Period Management

## Automatic vs Manual Control

### Automatic Period Creation (2 cases only):

1. **First Shareholder**
   - When adding capital deposit for the FIRST shareholder (no equity period exists)
   - System automatically creates Period 1 with that shareholder

2. **After Period Closure**
   - When you manually close a period using `CapitalService::closePeriodAndAllocate()`
   - System automatically creates a new period starting the next day
   - All current shareholders are added to the new period with their current percentages

### Manual Control:

- **Adding New Shareholders**: New shareholders are added to the OPEN period
- **Period Closure**: YOU decide when to close periods (manually trigger)
- **Equity Percentages**: Auto-recalculated when capital changes, but period stays open

## Workflow Examples

### Starting a Company:

1. Add Shareholder 1 (partner record only)
2. Add Shareholder 2 (partner record only)
3. Add Shareholder 3 (partner record only)
4. Add capital deposit for Shareholder 1 → **Period 1 auto-created with Shareholder 1**
5. Add capital deposit for Shareholder 2 → **Shareholder 2 added to Period 1, percentages recalculated**
6. Add capital deposit for Shareholder 3 → **Shareholder 3 added to Period 1, percentages recalculated**
7. Now Period 1 has all 3 shareholders with their correct equity percentages

### Monthly Operations:

- Period 1 stays OPEN for the entire month
- Business operates, generates revenue/expenses
- When month ends (or quarter, or year - your choice):
  - Manually call `closePeriodAndAllocate(end_date, "End of Month/Quarter/Year")`
  - System allocates profits to shareholders based on Period 1 percentages
  - System automatically creates Period 2 starting next day
  - All shareholders carry over to Period 2 with their current percentages

### Adding New Shareholder Mid-Period:

1. Period 2 is OPEN with existing shareholders
2. Add new Shareholder 4
3. Add capital deposit for Shareholder 4
4. Shareholder 4 is added to Period 2 (current OPEN period)
5. Equity percentages recalculated for ALL shareholders
6. Period 2 continues with 4 shareholders now
7. When you close Period 2, profits are distributed based on final percentages

## Technical Implementation

### CreateTreasuryTransaction (after capital deposit):
```php
1. Recalculate current_capital for the shareholder
2. Recalculate equity_percentage for ALL shareholders
3. If NO period exists → Create initial period
4. If period EXISTS → Add new shareholder to OPEN period (if not already in it)
```

### CapitalService::closePeriodAndAllocate():
```php
1. Calculate period profit
2. Allocate profit to shareholders based on locked percentages
3. Update current_capital for each shareholder (add profit)
4. Close the period
5. Auto-create new period starting next day with current shareholders
```

## Key Points

✅ First shareholder capital deposit → Auto-creates Period 1
✅ Closing a period → Auto-creates next period
✅ New shareholders → Added to OPEN period
✅ Equity percentages → Auto-recalculated on capital changes
❌ Period closure → MANUAL (you control when)
❌ New shareholders → Don't auto-close periods

## Database State Example

After following the workflow above:

### Partners Table:
```
| Name        | Capital  | Equity % |
|-------------|----------|----------|
| Shareholder 1 | 150,000 | 37.5%   |
| Shareholder 2 | 100,000 | 25.0%   |
| Shareholder 3 | 50,000  | 12.5%   |
| Shareholder 4 | 100,000 | 25.0%   |
```

### Equity Periods:
```
Period 1 (closed):
  - Shareholder 1: 50% (150,000)
  - Shareholder 2: 33.3% (100,000)
  - Shareholder 3: 16.7% (50,000)
  - Profit allocated based on these percentages

Period 2 (open):
  - Shareholder 1: 37.5% (150,000)
  - Shareholder 2: 25.0% (100,000)
  - Shareholder 3: 12.5% (50,000)
  - Shareholder 4: 25.0% (100,000) ← New shareholder
  - Future profits will use these percentages
```
