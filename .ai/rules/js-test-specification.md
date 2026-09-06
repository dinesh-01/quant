---
paths:
    - 'resources/js/{pages,components,lib}/**specification**'
---

# Test Specification UI

## Specification panes: key them, and never nest a form

Key the detail panes by the selected node in pages/test-specification/index.tsx (the case pane by version too). Both panes edit through uncontrolled inputs, and a defaultValue is only read on mount, so without the key React reuses the same inputs and keeps showing the previous node's text. This already caused a real bug: the version switcher appeared to do nothing.

The five rich text fields use components/rich-text/lazy-rich-text-editor when editable and RichText when not. A frozen version renders rather than edits: a contenteditable region that looks greyed out still takes keystrokes, so there is no disabled editor. Their Labels carry no htmlFor, because the editor is not a labellable control and names itself with aria-label.

Each reorder control is its own <form>, so it must sit OUTSIDE any surrounding form. The attachment list carries upload and delete forms of its own, which is why it is a card of its own rather than part of the pane's edit form. Nested forms are invalid HTML. This is why the step number and its reorder buttons are above the step's edit form rather than inside its header row.

Reorder endpoints take the FULL sibling order, not a delta. Derive it from the tree page prop via lib/specification-tree.ts, never from extra server props: the tree already carries it and a second source can only disagree. The tree prop's ordering is therefore load-bearing and has its own test in SpecificationPageTest.

Submit the order as repeated hidden `order[]` inputs. Inertia's Form collects repeated bracket names into an array, so no client state is needed and the buttons re-render from the fresh prop after the redirect.

The keyword filter is links only, never client state: every control is a `<Link>` to the current node's route with a `keywords[]`/`keyword_match` query, so the filter is shareable, the back button undoes it, and nothing can disagree with what the server applied. Render `keywordFilter` as the server echoes it back, because it drops ids from other projects rather than narrowing the tree to nothing.

Move pickers exclude invalid targets (a suite's own subtree) and stay disabled until the target changes, because moving a node under its current parent sends it to the end of its siblings rather than doing nothing.

The specification tree stores expansion as local state. Tree links must use `preserveState` (and `preserveScroll`) or an Inertia visit remounts the page and Collapse all snaps back open. Do not use an effect that re-adds the selected suite on every `suites`/`selected` identity change — that undoes a user collapse. Open a newly selected suite during render when the selected key changes.

Drag and drop reorders siblings only. Post the same full `order[]` the up/down buttons use (`TestSuiteController.reorder` / `TestCaseController.reorder`). Do not treat a drop on another suite as a move — re-parenting stays on the move pickers. Prefix sortable ids (`suite:1` / `case:1`) because both tables start at 1. The grip is the drag handle so the name remains a link. Viewers get no handle (`can.manage`). Hide the grip when a node has no sibling to swap with.
