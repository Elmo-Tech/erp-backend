# Calendar Visual Design Guide

## Premium Vercel-like Design System

This guide documents the visual design improvements achieved by migrating to FullCalendar.

---

## Design Philosophy

### Core Principles

1. **Clean & Minimal** - Remove visual clutter
2. **Premium Feel** - Subtle animations and shadows
3. **Consistent** - Match Shadcn/Tailwind design system
4. **Accessible** - WCAG 2.1 AA compliant
5. **Responsive** - Works on all screen sizes

---

## Color System

### Event Type Colors

```css
Event (Blue):    #3b82f6  /* blue-500 */
Task (Violet):   #8b5cf6  /* violet-500 */
Invoice (Amber): #f59e0b  /* amber-500 */
```

### Theme Colors (CSS Variables)

```css
--primary:              /* Primary brand color */ --primary-foreground: /* Text on primary */ --secondary: /* Secondary actions */ --secondary-foreground: /* Text on secondary */ --accent: /* Highlights */ --border: /* Grid lines */
	--muted-foreground: /* Subtle text */ --foreground: /* Main text */;
```

---

## Typography

### Font Hierarchy

```css
Title:        1.25rem (20px), font-weight: 600
Day Numbers:  0.75rem (12px), font-weight: 400
Day Headers:  0.6875rem (11px), font-weight: 600, uppercase
Event Text:   0.75rem (12px), font-weight: 500
Buttons:      0.875rem (14px), font-weight: 500
```

### Letter Spacing

```css
Day Headers: 0.05em (uppercase tracking)
Normal Text: 0 (default)
```

---

## Spacing & Layout

### Grid Spacing

```css
Cell Padding:     8px
Event Padding:    2px 4px
Button Padding:   0.375rem 0.75rem (6px 12px)
Section Gap:      1.5rem (24px)
```

### Border Radius

```css
Calendar Grid:  0.5rem (8px)
Buttons:        0.5rem (8px)
Events:         4px
Today Badge:    50% (circular)
```

---

## Interactive States

### Buttons

#### Default State

```css
background: hsl(var(--primary))
border: 1px solid hsl(var(--primary))
border-radius: 0.5rem
padding: 0.375rem 0.75rem
font-weight: 500
text-transform: capitalize
transition: all 0.2s ease
```

#### Hover State

```css
opacity: 0.9
transform: translateY(-1px)
box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1)
```

#### Active State

```css
background: hsl(var(--primary) / 0.8);
```

#### Disabled State

```css
opacity: 0.5
cursor: not-allowed
```

### Events

#### Default State

```css
background: [type-specific color]
border-radius: 4px
padding: 2px 4px
font-size: 0.75rem
cursor: pointer
transition: all 0.2s ease
```

#### Hover State

```css
transform: translateY(-1px)
box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1)
```

---

## Special Elements

### Today Cell

#### Day Number Badge

```css
display: inline-flex
align-items: center
justify-content: center
width: 24px
height: 24px
border-radius: 50%
background: hsl(var(--primary))
color: hsl(var(--primary-foreground))
font-weight: 600
```

#### Cell Background

```css
background: hsl(var(--accent) / 0.3);
```

### Current Time Indicator

```css
border-color: hsl(var(--primary))
border-width: 2px
```

### More Link

```css
font-size: 0.6875rem
color: hsl(var(--primary))
font-weight: 500
text-decoration: underline on hover
```

---

## Responsive Breakpoints

### Desktop (1920x1080)

- Full calendar with all features
- 3-column event layout in month view
- Expanded toolbar

### Laptop (1366x768)

- Full calendar with all features
- 2-column event layout in month view
- Compact toolbar

### Tablet (768x1024)

- Stacked layout
- Single-column event layout
- Simplified toolbar

### Mobile (375x667)

- Vertical layout
- List view recommended
- Minimal toolbar

---

## Accessibility Features

### Keyboard Navigation

- **Tab**: Navigate between interactive elements
- **Enter/Space**: Activate buttons and events
- **Arrow Keys**: Navigate calendar grid
- **Escape**: Close popovers and modals

### Screen Reader Support

- **ARIA Labels**: All interactive elements labeled
- **Role Attributes**: Proper semantic roles
- **Live Regions**: Dynamic content announced
- **Focus Indicators**: Visible focus states

### Color Contrast

- **Text on Primary**: 4.5:1 minimum
- **Text on Background**: 4.5:1 minimum
- **Interactive Elements**: 3:1 minimum

---

## Animation Guidelines

### Timing Functions

```css
Default:     ease (0.25s cubic-bezier)
Hover:       0.2s ease
Transform:   0.2s ease
Opacity:     0.2s ease
```

### Transform Effects

```css
Hover Lift:  translateY(-1px)
Click:       scale(0.98)
```

### Shadow Progression

```css
Default:     none
Hover:       0 4px 6px -1px rgb(0 0 0 / 0.1)
Active:      0 2px 4px -2px rgb(0 0 0 / 0.1)
```

---

## Component Anatomy

### Calendar Header

```
┌─────────────────────────────────────────────────┐
│  ← → Today    [Month Title]    Month Week Day   │
└─────────────────────────────────────────────────┘
```

### Month View Grid

```
┌───────────────────────────────────────────────┐
│ SUN   MON   TUE   WED   THU   FRI   SAT      │
├───────┬───────┬───────┬───────┬───────┬───────┤
│   1   │   2   │   3   │   4   │   5   │   6   │
│ [evt] │       │ [evt] │       │ [evt] │       │
├───────┼───────┼───────┼───────┼───────┼───────┤
│   7   │  (8)  │   9   │  10   │  11   │  12   │
│       │ [evt] │       │ [evt] │       │ [evt] │
└───────┴───────┴───────┴───────┴───────┴───────┘
```

_(8) = Today with circular badge_

### Event Pill

```
┌─────────────────────────┐
│ 🕐 Event Title          │
└─────────────────────────┘
```

---

## CSS Variable Reference

### Complete List

```css
/* Layout */
--fc-border-color: hsl(var(--border)) --fc-today-bg-color: hsl(var(--accent) / 0.3) /* Buttons */ --fc-button-bg-color: hsl(var(--primary)) --fc-button-border-color: hsl(var(--primary)) --fc-button-hover-bg-color: hsl(var(--primary) / 0.9)
	--fc-button-hover-border-color: hsl(var(--primary) / 0.9) --fc-button-active-bg-color: hsl(var(--primary) / 0.8) --fc-button-active-border-color: hsl(var(--primary) / 0.8) /* Typography */ --fc-small-font-size: 0.75rem
	--fc-page-bg-color: transparent --fc-neutral-bg-color: hsl(var(--muted) / 0.5);
```

---

## Design Tokens

### Shadows

```css
sm:  0 1px 2px 0 rgb(0 0 0 / 0.05)
md:  0 4px 6px -1px rgb(0 0 0 / 0.1)
lg:  0 10px 15px -3px rgb(0 0 0 / 0.1)
xl:  0 20px 25px -5px rgb(0 0 0 / 0.1)
```

### Transitions

```css
fast:    150ms
normal:  200ms
slow:    300ms
```

### Z-Index Layers

```css
base:     0
dropdown: 1000
sticky:   1020
fixed:    1030
modal:    1040
popover:  1050
tooltip:  1060
```

---

## Best Practices

### Do's ✅

- Use CSS variables for all colors
- Apply smooth transitions to interactive elements
- Maintain consistent spacing throughout
- Use semantic HTML elements
- Provide ARIA labels for accessibility
- Test on multiple screen sizes
- Ensure 4.5:1 color contrast minimum

### Don'ts ❌

- Don't use hard-coded colors
- Don't use abrupt state changes
- Don't mix spacing scales
- Don't use generic div elements
- Don't forget keyboard navigation
- Don't assume desktop-only usage
- Don't sacrifice accessibility for aesthetics

---

## Testing Checklist

### Visual Regression

- [ ] Compare screenshots before/after
- [ ] Check all interactive states
- [ ] Verify color contrast ratios
- [ ] Test dark mode (if applicable)
- [ ] Validate responsive breakpoints

### Accessibility Audit

- [ ] Run axe DevTools
- [ ] Test keyboard navigation
- [ ] Verify screen reader announcements
- [ ] Check focus indicators
- [ ] Validate ARIA attributes

### Cross-Browser Testing

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

---

## Resources

### Documentation

- [FullCalendar Docs](https://fullcalendar.io/docs)
- [Shadcn UI](https://ui.shadcn.com)
- [Tailwind CSS](https://tailwindcss.com)
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)

### Tools

- [Contrast Checker](https://webaim.org/resources/contrastchecker/)
- [axe DevTools](https://www.deque.com/axe/devtools/)
- [Lighthouse](https://developers.google.com/web/tools/lighthouse)

---

**Last Updated**: May 12, 2026
**Maintained By**: Frontend Team
