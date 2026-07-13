# Pryvora Frontend - Complete Documentation

## Overview

Pryvora is a modern productivity application built with React 19, Vite, and Tailwind CSS 4. The application features a dark-themed minimalist design with smooth animations powered by Framer Motion. It provides users with task management, note-taking, calendar functionality, and secure authentication with 2FA support.

---

## Technology Stack

### Core Technologies
- **React**: 19.2.0 (Latest)
- **Vite**: 7.3.1 (Build tool & dev server)
- **React Router DOM**: 7.13.0 (Client-side routing)
- **Tailwind CSS**: 4.2.0 (Utility-first CSS framework)
- **TypeScript**: No (Using JavaScript with JSDoc)

### UI Libraries & Components
- **shadcn/ui**: new-york style (Component library)
- **Radix UI**: 1.4.3 (Unstyled accessible primitives)
- **Lucide React**: 0.575.0 (Icon library)
- **Framer Motion**: 12.34.3 (Animation library)
- **class-variance-authority**: 0.7.1 (Component variants)
- **clsx & tailwind-merge**: Utility for className merging

### Date & Time
- **date-fns**: 4.1.0 (Date manipulation)
- **react-day-picker**: 9.14.0 (Calendar component)

### Development Tools
- **ESLint**: 9.39.1 (Code linting)
- **PostCSS**: 8.5.6 (CSS processing)
- **Autoprefixer**: 10.4.24 (CSS vendor prefixes)

---

## Project Structure

```
frontend/
├── public/                     # Static assets
├── src/
│   ├── assets/                 # Images, fonts, etc.
│   ├── components/
│   │   ├── layout/             # Layout components
│   │   │   ├── AppLayout.jsx   # Main app layout wrapper
│   │   │   ├── Sidebar.jsx     # Navigation sidebar
│   │   │   └── Topbar.jsx      # Top header bar
│   │   ├── ui/                 # shadcn/ui components
│   │   │   ├── alert.jsx
│   │   │   ├── avatar.jsx
│   │   │   ├── badge.jsx
│   │   │   ├── button.jsx
│   │   │   ├── calendar.jsx
│   │   │   ├── card.jsx
│   │   │   ├── checkbox.jsx
│   │   │   ├── dialog.jsx
│   │   │   ├── input.jsx
│   │   │   ├── label.jsx
│   │   │   ├── select.jsx
│   │   │   ├── separator.jsx
│   │   │   └── textarea.jsx
│   │   ├── DatePicker.jsx      # Custom date picker wrapper
│   │   ├── ProtectedRoute.jsx  # Auth route guard
│   │   ├── Search.jsx          # Global search component
│   │   ├── TagSelector.jsx     # Tag selection/creation
│   │   ├── TaskForm.jsx        # Task create/edit form
│   │   ├── TaskItem.jsx        # Task card component
│   │   └── theme-provider.jsx  # Dark/light theme context
│   ├── contexts/
│   │   └── AuthContext.jsx     # Authentication state & API
│   ├── hooks/
│   │   ├── use-mobile.js       # Mobile breakpoint detection
│   │   └── use-reduced-motion.js # Prefers-reduced-motion
│   ├── lib/
│   │   └── utils.js            # cn() utility function
│   ├── pages/
│   │   ├── Calendar.jsx        # Calendar page (WIP)
│   │   ├── Dashboard.jsx       # Main dashboard
│   │   ├── Login.jsx           # Login page
│   │   ├── Notes.jsx           # Notes management
│   │   ├── Register.jsx        # Registration page
│   │   ├── Settings.jsx        # User settings
│   │   └── Tasks.jsx           # Task management
│   ├── App.jsx                 # Main app component & routes
│   ├── main.jsx                # Entry point
│   └── index.css               # Global styles & Tailwind
├── components.json             # shadcn/ui configuration
├── index.html                  # HTML entry point
├── package.json                # Dependencies
├── vite.config.js              # Vite configuration
└── jsconfig.json               # JavaScript config (path aliases)
```

---

## Design System

### Color Palette

#### Base Colors (Dark Theme)
- **Background**: `#0f0f0f` (Main app background)
- **Surface**: `#1a1a1a` (Cards, panels, elevated surfaces)
- **Hover Surface**: `#222222` / `#252525` (Hover states)
- **Border**: `#1a1a1a` to `#2a2a2a` (Subtle borders)
- **Primary Text**: `#e5e5e5` (Headings, primary content)
- **Secondary Text**: `#888888` (Descriptions, metadata)
- **Muted Text**: `#666666` (Tertiary information)

#### Semantic Colors
- **Success/Green**: `#10b981` (Emerald) - Completed tasks, success states
- **Warning/Amber**: `#f59e0b` (Amber) - Medium priority, warnings
- **Danger/Red**: `#ef4444` (Red) - Overdue tasks, errors, high priority
- **Info/Blue**: `#3b82f6` (Blue) - In-progress tasks, links
- **Primary/Indigo**: `#6366f1` (Indigo) - Primary actions, accents

#### Status Colors (Tasks)
```javascript
const status_colors = {
  todo: 'bg-zinc-700 text-zinc-200 border-zinc-500',
  in_progress: 'bg-indigo-950 text-indigo-300 border-indigo-700',
  done: 'bg-emerald-950 text-emerald-400 border-emerald-700',
}
```

#### Priority Colors
```javascript
const priority_colors = {
  low: 'bg-slate-800 text-slate-400 border-slate-600',
  medium: 'bg-amber-900/60 text-amber-300 border-amber-600',
  high: 'bg-rose-900/60 text-rose-300 border-rose-600',
}
```

### Typography

#### Font Stack
```css
font-family: system-ui, -apple-system, sans-serif;
```

#### Type Scale
- **Page Titles**: `text-3xl` (30px) - Dashboard greeting
- **Section Headers**: `text-2xl` (24px) - Page headers
- **Card Titles**: `text-lg` (18px) - Card headers
- **Body Text**: `text-sm` (14px) - Default content
- **Small/Caption**: `text-xs` (12px) - Metadata, hints

#### Font Weights
- **Semibold**: `font-semibold` (600) - Headings, emphasis
- **Medium**: `font-medium` (500) - Labels, buttons
- **Regular**: `font-normal` (400) - Body text

### Spacing System

Tailwind's default spacing scale is used:
- **Layout Padding**: `p-6` (24px), `p-8` (32px)
- **Component Padding**: `px-3` (12px), `px-4` (16px)
- **Gap**: `gap-2` (8px), `gap-3` (12px), `gap-4` (16px), `gap-6` (24px)
- **Margin**: `mb-1` to `mb-6` (4px to 24px)

### Border Radius
- **Small**: `rounded-md` (6px) - Buttons, inputs
- **Medium**: `rounded-lg` (8px) - Cards, modals
- **Large**: `rounded-xl` (12px) - Large modals
- **Full**: `rounded-full` - Badges, avatars

### Shadows & Elevation
- **Subtle**: `shadow-sm` - Cards
- **Medium**: `shadow-md` - Elevated cards
- **Large**: `shadow-lg`, `shadow-xl` - Modals, dropdowns
- **Custom**: `shadow-2xl shadow-black/50` - Search results

---

## Component Architecture

### Layout Components

#### AppLayout
**Purpose**: Main application shell wrapping all authenticated pages.

**Structure**:
- Fixed Sidebar (left, 256px width)
- Flexible content area with Topbar
- Full viewport height (`h-screen`)
- Hidden overflow for scrollable main content

**Props**:
- `children`: Page content
- `title`: Page title for Topbar

**Visual**:
- Background: `#0f0f0f`
- Sidebar border: `#1a1a1a`

#### Sidebar
**Purpose**: Primary navigation component.

**Features**:
- App branding ("Pryvora" logo text)
- Navigation links (Dashboard, Notes, Tasks, Calendar, Settings)
- Active state highlighting
- User info display (email)
- Logout button

**Animation**:
- Hover states with Framer Motion
- Scale and color transitions
- Duration: 150ms

**Active State**:
- Background: `#1a1a1a`
- Text: `#e5e5e5`

**Inactive State**:
- Text: `#888888`

#### Topbar
**Purpose**: Header bar with page title, search, and user avatar.

**Features**:
- Page title (animated entrance)
- Centered global search component
- User avatar (initial fallback)

**Animation**:
- Title: Fade + slide from left (250ms, ease-out)
- Avatar: Fade + scale (250ms)

**Height**: 64px (`h-16`)

---

### UI Components (shadcn/ui)

All UI components are built on Radix UI primitives with Tailwind CSS styling.

#### Button
**Variants**:
- `default`: Primary actions (white background)
- `destructive`: Danger actions (red)
- `outline`: Secondary actions (border only)
- `ghost`: Tertiary actions (hover background)
- `link`: Text links

**Sizes**: `xs`, `sm`, `default`, `lg`, `icon`, `icon-sm`, `icon-lg`

#### Input
**Styles**:
- Background: Transparent with dark surface
- Border: `#2a2a2a`
- Focus: Ring with accent color
- Height: 36px (`h-9`)

#### Card
**Structure**:
- `CardHeader`: Title + Description
- `CardContent`: Main content area
- `CardFooter`: Actions

**Styles**:
- Background: `#0f0f0f`
- Border: `#1a1a1a`
- Padding: 24px (`p-6`)
- Border radius: 12px (`rounded-xl`)

#### Dialog (Modal)
**Features**:
- Radix UI Dialog primitive
- Overlay with backdrop blur
- Animation: Fade + zoom (200ms)
- Max width: 500px (sm), 540px (lg)

**Structure**:
- `DialogHeader`: Title + Description
- Content area
- `DialogFooter`: Actions

#### Badge
**Variants**: Default, Secondary, Destructive, Outline, Ghost, Link

**Styles**:
- Pill shape (`rounded-full`)
- Small text (`text-xs`)
- Minimal padding

#### Avatar
**Size**: 32px default, 40px large
**Shape**: Circular
**Fallback**: User initial letter

#### Calendar
**Library**: react-day-picker
**Theme**: Dark customized
**Selected**: Indigo background
**Today**: Indigo ring
**Hover**: White/10 background

---

### Feature Components

#### Search
**Purpose**: Global search for notes and tasks.

**Features**:
- Debounced search (300ms)
- Real-time results dropdown
- Result type icons (Note/Task)
- Keyboard accessible
- Clear button with animation

**Visual**:
- Input height: 48px
- Background: `#1a1a1a`
- Dropdown: Backdrop blur, shadow-2xl

**Animation**:
- Results: Stagger fade-in (30ms delay each)
- Icons: Rotate on loading
- No results: Scale animation

#### TagSelector
**Purpose**: Select or create tags for notes/tasks.

**Features**:
- Display selected tags as badges
- Dropdown of available tags
- Create new tag dialog
- Color picker for new tags

**Visual**:
- Tag badges with custom colors
- Dropdown: Max height 48px scrollable

#### TaskForm
**Purpose**: Create or edit tasks.

**Fields**:
- Title (required)
- Description (textarea)
- Status (To Do, In Progress, Done)
- Priority (Low, Medium, High) - Button group
- Due Date (quick select + date picker)

**Quick Date Options**:
- Today, Tomorrow, In 3 days, In a week, In a month

**Visual**:
- Minimal input styling (border-bottom only for text fields)
- Priority buttons with state-based styling
- Dialog modal presentation

#### TaskItem
**Purpose**: Display individual task in grid.

**Features**:
- Checkbox for status toggle
- Title and description
- Status and priority badges
- Due date with overdue indicator
- Edit and delete actions (hover)
- Delete confirmation modal

**Visual**:
- Fixed height: 192px (12 * 16)
- Left border color by status
- Hover: Lift and border highlight
- Completed: 50% opacity, strikethrough

**Card Structure**:
- Header: Checkbox + Title + Actions
- Body: Description (clamped to 3 lines)
- Footer: Badges + Due date

#### DatePicker
**Purpose**: Select dates for tasks.

**Features**:
- Opens calendar dialog
- Displays formatted selected date
- Clear/placeholder state

**Visual**:
- Full width button trigger
- Calendar in modal (400px max width)

#### ProtectedRoute
**Purpose**: Route guard for authenticated pages.

**Logic**:
- Shows loading state while checking auth
- Redirects to `/login` if not authenticated
- Renders children if authenticated

---

### Page Components

#### Dashboard
**Purpose**: Main overview page after login.

**Sections**:
1. **Greeting**: Time-based (Good morning/afternoon/evening)
   - User's first name
   - Current date (formatted: "Monday, January 1, 2024")
   - Quick stats badges (Today's tasks, Upcoming, Notes count)

2. **Overdue Tasks**: Red-themed section
   - List of overdue incomplete tasks
   - Empty state if none

3. **Today's Tasks**: Tasks due today
   - Inline checkboxes
   - Priority indicators
   - Quick toggle

4. **Upcoming Tasks**: Next 7 days
   - Grouped by date
   - Date headers (Today, Tomorrow, or full date)
   - Sorted chronologically

5. **Recent Notes** (Sidebar):
   - Grid of note cards (2 columns)
   - Title + content preview
   - Tag badges

**Layout**:
- 2-column grid (2/3 tasks, 1/3 notes)
- Notes sidebar sticky on large screens
- Max width: 1600px

**Animation**:
- Page fade + slide up (300ms)
- Section stagger (250ms)
- Task cards: Individual fade (200ms)

#### Notes
**Purpose**: Create, view, edit, and delete notes.

**Features**:
- Grid layout (3 columns on large screens)
- Create note modal
- Edit note modal
- Delete confirmation
- Tag management
- Success/error alerts

**Note Card**:
- Title (truncated)
- Updated/created timestamp
- Content preview (4 lines max)
- Tag badges
- Edit/Delete buttons (hover reveal)

**Empty State**:
- Large emoji (📝)
- "No notes yet" message
- Create button

**Animation**:
- Cards: Stagger fade + slide (50ms delay each)
- Hover: Lift -4px
- Modal: Scale + fade (200-300ms)

#### Tasks
**Purpose**: Comprehensive task management.

**Features**:
- Category tabs: All, Today, Overdue
- Status filter tabs: All, To Do, In Progress, Done
- Task grid (responsive: 2-5 columns)
- Create task modal
- Edit task modal
- Delete confirmation

**Category Tabs**:
- **All**: Default, white theme
- **Today**: Blue theme
- **Overdue**: Red theme

**Status Filter**:
- All Status: Zinc
- To Do: Zinc
- In Progress: Indigo
- Done: Emerald

**Task Grid**:
- Responsive columns:
  - 2 cols (mobile)
  - 3 cols (md)
  - 4 cols (lg)
  - 5 cols (xl)

**Empty States**:
- Icon based on current filter
- Contextual message

#### Calendar
**Purpose**: Calendar view (Under development).

**Current State**:
- Placeholder card
- "Coming soon" message

#### Settings
**Purpose**: User account settings and preferences.

**Sections**:

1. **Two-Factor Authentication**:
   - Enable/disable 2FA
   - QR code setup flow (3 steps)
   - Manual secret entry option
   - Verification code input
   - Recovery codes display
   - Download/copy recovery codes

2. **Change Password**:
   - Current password
   - New password
   - Confirm password
   - Validation (min 6 chars, match check)

3. **Manage Tags**:
   - List of all user tags
   - Edit tag (name + color)
   - Delete tag confirmation
   - Color preview circles

**2FA Setup Flow**:
- **Step 1**: Scan QR code
- **Step 2**: Manual entry (secret key)
- **Step 3**: Verify with code

**Visual**:
- Cards with dark theme
- Success: Green alert
- Error: Red alert
- 2FA Active: Green status indicator

#### Login
**Purpose**: User authentication.

**Features**:
- Email input
- Password input
- 2FA verification flow
- Recovery code option
- Link to registration

**2FA Flow**:
1. Enter credentials
2. If 2FA enabled: Show OTP input
3. Option to switch to recovery code
4. Verify and redirect to dashboard

**Visual**:
- Centered card layout
- Dark background (`#0a0a0a`)
- Full-width submit button
- Separator with "or" alternative

#### Register
**Purpose**: New user registration.

**Fields**:
- First name
- Last name
- Email
- Password
- Confirm password

**Validation**:
- All fields required
- Password min 6 characters
- Passwords must match
- Auto-login after successful registration

**Visual**:
- Similar to login page
- Card centered on dark background

---

## Authentication System

### AuthContext

**State**:
- `user`: Current user object (null if not authenticated)
- `loading`: Auth check in progress
- `access_token`: Stored in sessionStorage

**Methods**:
- `login(email, password)`: Authenticate user
- `register(first_name, last_name, email, password)`: Create account
- `logout()`: End session
- `verify_2fa(code, is_recovery_code)`: Complete 2FA
- `check_auth()`: Verify current session
- `api_request(url, options)`: Authenticated fetch wrapper

### Token Management

**Storage**: sessionStorage (access_token only)

**Flow**:
1. Login → Get token → Store in sessionStorage
2. API request → Add Authorization header
3. 401 response → Auto refresh token
4. Refresh fails → Clear session → Redirect to login

### API Endpoints Used

```
POST /api/auth/register       - Registration
POST /api/auth/login          - Login
POST /api/auth/verify-2fa     - 2FA verification
POST /api/auth/refresh        - Token refresh
POST /api/auth/logout         - Logout
GET  /api/user/me             - Get current user
POST /api/user/change-password - Change password
```

---

## API Integration

### Base URL
```javascript
import.meta.env.VITE_API_URL  // Typically http://localhost:8000
```

### Request Pattern

```javascript
const response = await api_request(`${API_BASE_URL}/api/endpoint`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(data),
})
```

### Auto-Refresh Logic

The `api_request` method in AuthContext handles:
1. Adding Authorization header if token exists
2. Catching 401 responses
3. Calling refresh endpoint
4. Retrying original request with new token
5. Throwing error if refresh fails

### Endpoints by Feature

#### Dashboard
```
GET /api/dashboard/  - Overview data (tasks, notes)
```

#### Tasks
```
GET    /api/task/     - List all tasks
POST   /api/task      - Create task
PUT    /api/task/:id  - Update task
DELETE /api/task/:id  - Delete task
```

#### Notes
```
GET    /api/notes/     - List all notes
POST   /api/notes/     - Create note
PUT    /api/notes/:id  - Update note
DELETE /api/notes/:id  - Delete note
```

#### Tags
```
GET    /api/tag/      - List all tags
POST   /api/tag/      - Create tag
PATCH  /api/tag/:id   - Update tag
DELETE /api/tag/:id   - Delete tag
```

#### Search
```
GET /api/search?query=:query  - Search notes and tasks
```

#### 2FA
```
POST /api/2fa/setup   - Generate QR and secret
POST /api/2fa/enable  - Enable with verification code
POST /api/2fa/disable - Disable with verification code
```

---

## State Management

### React Hooks

**useState**: Local component state
**useEffect**: Side effects, data fetching
**useContext**: Global state (Auth, Theme)

### Context Providers

#### AuthContext
- User authentication state
- API request wrapper
- Token management

#### ThemeProvider
- Theme state (dark/light)
- localStorage persistence
- Default: "dark"

### Custom Hooks

#### use_reduced_motion()
Detects `prefers-reduced-motion` media query and returns boolean for animation adjustments.

#### useIsMobile()
Detects viewport width < 768px and returns boolean for responsive layouts.

---

## Animation System

### Framer Motion Configuration

#### Page Transitions
```javascript
const page_variants = {
  hidden: { opacity: 0, y: 20 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      duration: 0.5,
      ease: [0.22, 1, 0.36, 1], // Custom ease
    },
  },
}
```

#### Card Stagger
```javascript
const container_variants = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: {
      staggerChildren: 0.1,
      delayChildren: 0.2,
    },
  },
}
```

#### Hover Animations
- Scale: 1.02 - 1.05
- Y translation: -4px lift
- Duration: 150-200ms
- Ease: Custom cubic-bezier

#### Modal Animations
- Fade + Zoom (zoom-in-95)
- Duration: 200-300ms
- Scale: 0.95 → 1

### Reduced Motion

When `prefers-reduced-motion: reduce` is detected:
- Animation duration: 0.01ms
- Transition duration: 0.01ms
- Scroll behavior: auto (not smooth)
- Animation iteration: 1 (no loops)

---

## Responsive Design

### Breakpoints (Tailwind Default)

```css
sm: 640px   /* Small tablets */
md: 768px   /* Tablets */
lg: 1024px  /* Laptops */
xl: 1280px  /* Desktops */
2xl: 1536px /* Large screens */
```

### Layout Responses

#### Sidebar
- Fixed width: 256px (all screens)
- Hidden on mobile (future enhancement)

#### Task Grid
```
grid-cols-2   /* Mobile: 2 columns */
md:grid-cols-3  /* Tablet: 3 columns */
lg:grid-cols-4  /* Laptop: 4 columns */
xl:grid-cols-5  /* Desktop: 5 columns */
```

#### Notes Grid
```
grid-cols-1   /* Mobile: 1 column */
md:grid-cols-2  /* Tablet: 2 columns */
lg:grid-cols-3  /* Desktop: 3 columns */
```

#### Dashboard
```
lg:grid-cols-3  /* 2/3 + 1/3 split on large screens */
```

---

## Accessibility

### ARIA & Roles
- Radix UI components include proper ARIA attributes
- Dialog: `role="dialog"`, `aria-modal`
- Alerts: `role="alert"`
- Buttons: Proper `aria-label` where needed

### Keyboard Navigation
- All interactive elements focusable
- Tab order follows visual layout
- Escape closes modals
- Enter submits forms

### Screen Reader Support
- `sr-only` class for visually hidden text
- Icon buttons have accessible labels
- Form labels associated with inputs

### Focus Management
- Focus rings on interactive elements
- Focus trap in modals
- Return focus on modal close

---

## Performance Optimizations

### Code Splitting
- React.lazy for route-based splitting (future)
- Dynamic imports for heavy components

### Rendering
- React.memo for expensive components (future)
- useCallback for event handlers
- useMemo for computed values

### Images & Assets
- SVG icons (Lucide) - lightweight
- No external image dependencies
- System fonts (no font loading)

### Bundle Size
- Tree-shaking with Vite
- Minimal dependencies
- shadcn/ui: Only import used components

---

## Development Workflow

### Commands

```bash
npm run dev      # Start dev server (port 5173)
npm run build    # Production build
npm run preview  # Preview production build
npm run lint     # ESLint check
```

### Docker Integration

```bash
docker compose exec frontend npm run dev
docker compose exec frontend npm run build
```

### Environment Variables

Required in `.env`:
```
VITE_API_URL=http://localhost:8000
```

### Code Style

**Naming**: snake_case for functions/variables
```javascript
const handle_submit = () => {}
const fetch_data = async () => {}
```

**Braces**: Allman style
```javascript
if (condition)
{
  do_something();
}
```

**Comments**: Minimal, only for complex logic

---

## Browser Support

### Target Browsers
- Chrome/Edge (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)

### Features Used
- CSS Grid & Flexbox
- CSS Custom Properties (variables)
- ES2020+ (optional chaining, nullish coalescing)
- Intersection Observer (future features)

---

## Known Limitations

1. **Calendar Page**: Not yet implemented (placeholder only)
2. **Mobile Sidebar**: No hamburger menu for small screens
3. **Offline Support**: No PWA functionality
4. **Real-time Updates**: No WebSocket integration yet
5. **File Uploads**: No attachment support in notes
6. **Pagination**: Infinite scroll not implemented for large lists

---

## Future Enhancements

### Planned Features
- [ ] Calendar implementation
- [ ] Mobile responsive sidebar toggle
- [ ] Drag-and-drop task reordering
- [ ] Task dependencies
- [ ] Recurring tasks
- [ ] Note attachments
- [ ] Rich text editor for notes
- [ ] Export/import data
- [ ] Dark/Light theme toggle UI
- [ ] Keyboard shortcuts
- [ ] Command palette (Cmd/Ctrl + K)
- [ ] Notifications system
- [ ] Activity log/audit trail

### Technical Improvements
- [ ] TypeScript migration
- [ ] React Query for data fetching
- [ ] Zustand/Jotai for state management
- [ ] Storybook for component documentation
- [ ] Vitest/React Testing Library for tests
- [ ] E2E tests with Playwright
- [ ] Performance monitoring
- [ ] Error tracking (Sentry)

---

## File Reference Summary

### Entry Points
- `main.jsx`: React root render
- `index.html`: HTML shell
- `index.css`: Global styles + Tailwind

### Core Files
- `App.jsx`: Router + auth guards
- `AuthContext.jsx`: Auth state + API wrapper
- `theme-provider.jsx`: Theme context

### Layout Files
- `AppLayout.jsx`: Shell wrapper
- `Sidebar.jsx`: Navigation
- `Topbar.jsx`: Header

### Page Files
- `Dashboard.jsx`: Overview page
- `Tasks.jsx`: Task CRUD
- `Notes.jsx`: Note CRUD
- `Calendar.jsx`: Placeholder
- `Settings.jsx`: User settings
- `Login.jsx`: Auth form
- `Register.jsx`: Registration form

### Component Files
- `Search.jsx`: Global search
- `TagSelector.jsx`: Tag management
- `TaskForm.jsx`: Task form
- `TaskItem.jsx`: Task card
- `DatePicker.jsx`: Date input
- `ProtectedRoute.jsx`: Auth guard

### Utility Files
- `utils.js`: cn() helper
- `use-reduced-motion.js`: Motion detection
- `use-mobile.js`: Mobile detection

---

## Design Principles

1. **Minimalism**: Clean, uncluttered interfaces
2. **Consistency**: Unified color palette, spacing, typography
3. **Dark-First**: Optimized for dark mode
4. **Subtle Animation**: Purposeful motion, not distracting
5. **Accessibility**: WCAG 2.1 AA compliance goal
6. **Performance**: Fast initial load, smooth interactions
7. **Progressive Enhancement**: Works without JS, better with it

---

## Summary

Pryvora frontend is a modern, well-structured React application with a focus on clean design, smooth animations, and user experience. The codebase follows consistent patterns, uses industry-standard libraries, and maintains a clear separation of concerns. The dark theme creates a professional, focused atmosphere suitable for productivity tasks.

**Key Strengths**:
- Modern React 19 with hooks
- Comprehensive component library (shadcn/ui + Radix)
- Smooth, purposeful animations
- Consistent design system
- Accessible components
- Clean code organization

**Areas for Improvement**:
- TypeScript adoption
- Test coverage
- Mobile responsiveness
- Real-time features
- Advanced task features

This documentation provides a complete overview for designers and developers to understand, redesign, or extend the application.
