# Tax figures

What the application computes, and what it does not.

## Tax figures

Under *Settings → Tax values* a band can say that it uses the German
small-business rule (§ 19 UStG). The treasury then counts turnover against
both limits and warns before one is crossed — fees, merch and sold gear
count, member deposits do not, because they are contributions rather than
sales.

Every figure is a setting, because legislatures move them and because not
every band sits in Germany. The defaults are the German position as of July
2026: **25.000 €** for the previous year and **100.000 €** for the current
one, and **800 €** net as the line above which equipment is written off over
its useful life rather than at once (§ 6 Abs. 2 EStG).

Each year has an overview under *Treasury → Tax overview*: income and
expenses by category with the entries behind them, and the purchases that
belong in neither — a device above the low-value line spreads over its useful
life, counted from the month of purchase, so a purchase never sits in the
expenses as well. Both the line and the useful life are settings. Everyone
gets their own private entries; whoever keeps the treasury can also switch to
the band's figures. Neither view contains the other, and no member ever sees
another's private purchases. The sheet prints, and it exports as a table — or as a package that carries the receipts with it: the attachments of that year's entries and the invoices of equipment still being written off, whose paper sits in the year of the purchase. An invoice covering several devices is enclosed once.

Each value carries the date it was last confirmed. The system check says so
once that date is more than a year old, and a new release may ship better
defaults but never overwrites a figure a band has set — the release notes
name any change. There is no automatic lookup: no reliable machine-readable
source exists for this, and a band's server should not depend on one.

This is arithmetic, not tax advice.
