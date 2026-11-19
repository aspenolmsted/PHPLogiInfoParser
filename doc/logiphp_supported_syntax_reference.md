# LogiPHP Supported Syntax Reference

This document consolidates all Logi Info XML elements, attributes, token scopes, theme classes, and runtime parameters currently supported by the **LogiPHP** rendering engine.

---

## 1. Application Settings (`_Settings.lgx`)

The `_Settings.lgx` file defines application-wide constants and named database connections loaded lazily at runtime via `ConnectionManager::loadSettingsFile()`.

### `<Setting>` (Root)
* **`ID`**: Identifier for the settings file (e.g., `_Settings`).

### `<Constants>` & `<Constant>`
Defines global key-value pairs accessible across all reports via `@Constant.<ID>~`.
* **`ID`** *(required)*: Constant identifier.
* **`Value`** *(required)*: String value substituted when resolved.

```xml
<Constants>
  <Constant ID="AppTitle" Value="My Guitar Shop Analytics" />
  <Constant ID="DefaultPageSize" Value="20" />
</Constants>
```

### `<Connections>` & `<Connection>` (or `<Connection.<Type>>`)
Defines named data sources referenced by `DataLayer` elements via `ConnectionID`. Both `<Connection ... Type="MySQL"/>` and `<Connection.MySQL .../>` syntax forms are supported. PDO instances are opened lazily only when queried.
* **`ID`** *(required)*: Connection identifier (e.g., `connMyGuitarShop`).
* **`Type`**: Database driver (`MySQL`, `MariaDB`, `PostgreSQL`/`Postgres`/`PgSQL`, `SqlServer`/`MSSQL`, `SQLite`, or `PDO`).
* **`Server`** *(or `Host`)*: Database hostname or IP address (default: `127.0.0.1`).
* **`Port`**: TCP port (defaults: `3306` for MySQL, `5432` for PostgreSQL).
* **`Database`** *(or `DbName`)*: Database name, or file path for `SQLite`.
* **`User`** *(or `Username`)*: Database username.
* **`Password`**: Database password.
* **`Charset`**: Character encoding for MySQL/MariaDB (default: `utf8mb4`).
* **`Dsn`**: Full raw PDO DSN string when `Type="PDO"`.

```xml
<Connections>
  <Connection Type="MySQL"
              ID="connMyGuitarShop"
              Server="127.0.0.1"
              Port="3306"
              Database="my_guitar_shop"
              User="root"
              Password=""
              Charset="utf8mb4" />
</Connections>
```

---

## 2. Token Resolution (`LogiContext`)

Tokens use Logi Info's `@Scope.Key~` syntax and are resolved dynamically inside text and query attributes (`Caption`, `Header`, `ChartCaption`, `AxisCaption`, `Source`, `Value`, `DefaultValue`, etc.).

| Token Syntax | Source | Escaping Behavior |
| :--- | :--- | :--- |
| **`@Data.<ColumnName>~`** | Current row in an active `DataTable` iteration | HTML-escaped (`ENT_QUOTES`, UTF-8) |
| **`@Request.<ParamName>~`** | HTTP query/request parameters (`$_GET`) & `<DefaultRequestParams>` | HTML-escaped in UI attributes; unescaped in SQL `Source` / `SqlParameter` |
| **`@Session.<KeyName>~`** | Active PHP session variables (`$_SESSION`) | HTML-escaped in UI attributes; unescaped in SQL `Source` / `SqlParameter` |
| **`@Constant.<ID>~`** | Global `<Constant>` definitions in `_Settings.lgx` | HTML-escaped in UI attributes; unescaped in SQL `Source` / `SqlParameter` |

---

## 3. Report Structure, Parameters & Catalog Metadata (`.lgx`)

### `<Report>` (Root)
* **`ID`**: Internal report identifier (defaults to filename if omitted).
* **`Caption`** *(or `Title`)*: Display title used in `<title>` and the Reports Directory portal.
* **`Category`**: Grouping heading in the Reports Directory (defaults to parent subfolder name or `General`).
* **`Description`**: Summary text displayed on the report card in the directory portal.
* **`SecurityRightID`**: Comma-separated list of required roles/rights (e.g., `SalesUser,Admin`). Reports with `SecurityRightID` are only listed when the user holds at least one matching right.
* **`ShowInMenu`**: `True` | `False` (default: `True`). Controls visibility in `ReportCatalog::listReports()`.
* **`IdeDisplay`**: Supported on any element; setting `IdeDisplay="False"` suppresses rendering of that element and its children.

### `<ReportMeta>` *(Optional Child of `<Report>`)*
Ignored during HTML rendering; read by `ReportCatalog` for portal ordering and UI hints.
* **`SortOrder`**: Integer sort weight within a category (default: `100`).
* **`Icon`**: Icon identifier string stored in catalog metadata.

### `<DefaultRequestParams>` *(Child of `<Report>`)*
Defines fallback request parameters when a key is missing or empty (`''`) in `$_GET`. Ignored during visual HTML rendering.
```xml
<DefaultRequestParams CategoryID="0" SearchTerm="" />
```

### `<Body>` & `<ideTestParams>`
* **`<Body>`**: Primary container for visual report elements.
* **`<ideTestParams>`**: Design-time sample parameter values used in Logi Studio; safely ignored at runtime.

---

## 4. Layout & Text Elements

### `<Division>`
Renders a structural HTML container (`<div>` or `<span>`) and recursively renders child elements.
* **`ID`**: HTML `id` attribute.
* **`Class`**: HTML `class` attribute.
* **`HtmlDiv`**: `True` renders a `<div>`; `False` or omitted renders a `<span>`.

### `<Label>`
Renders text or HTML content with token substitution.
* **`ID`**: HTML `id` attribute.
* **`Caption`**: Text or token expression to display.
* **`Class`**: CSS class name(s).
* **`Format`**: Set to `HTML` to output unescaped HTML markup; otherwise HTML-escaped inside a `<span>`.

### `<LineBreak>`
* **`LineCount`**: Number of `<br>` tags to emit (default: `1`).

---

## 5. Form Controls, Inputs & Event Actions

In Logi Info, an input control's **`ID`** attribute serves as both its HTML `id` and its `name` parameter key (`@Request.<ID>~`). Parameter resolution precedence is: `$_GET` $\rightarrow$ `<DefaultRequestParams>` $\rightarrow$ input `DefaultValue`.

### `<Form>`
Renders an HTML `<form>` container styled as a horizontal filter bar.
* **`ID`**: HTML `id` attribute (default: `frmLogi`).
* **`Method`**: `GET` (default) | `POST`.
* **`Action`**: Form target URL (defaults to current URL `""`).

### `<InputHidden>`
Renders a hidden input field—commonly used to preserve `rdReport` across `GET` form submissions.
* **`ID`** *(required)*: Parameter name.
* **`DefaultValue`**: Value emitted if `@Request.<ID>~` is not already set (supports token resolution).

### `<InputSelectList>`
Renders a `<select>` dropdown populated by a child `<DataLayer>` and automatically selects the option matching `@Request.<ID>~`.
* **`ID`** *(required)*: Parameter name (`@Request.<ID>~`).
* **`Caption`**: Optional `<label>` text rendered before the dropdown.
* **`OptionCaptionColumn`** *(required)*: Dataset column displayed as option text.
* **`OptionValueColumn`** *(required)*: Dataset column submitted as the option `value`.
* **`IncludeBlank`**: `True` | `False`. Prepends an empty option (`value=""`).
* **`IncludeBlankCaption`**: Text for the blank option (default: `-- All --`).
* **`DefaultValue`**: Fallback selected value if `@Request.<ID>~` is unset.
* **Child `<DataLayer>`**: Executes any supported `DataLayer` (`SQL` or `Static`) to populate options.
* **Child `<EventHandler>`**: Optional client-side event trigger.

### `<InputText>`
Renders a text `<input type="text">` box pre-filled with `@Request.<ID>~`.
* **`ID`** *(required)*: Parameter name (`@Request.<ID>~`).
* **`Caption`**: Optional `<label>` text rendered before the input.
* **`DefaultValue`**: Fallback text value if `@Request.<ID>~` is unset.

### `<Button>`
Renders a `<button type="submit">` to submit the enclosing `<Form>`.
* **`ID`**: HTML `id` attribute (default: `btnSubmit`).
* **`Caption`**: Button label text (default: `Submit`).
* **`Class`**: Optional CSS class name(s).

### `<EventHandler>` & `<Action>` *(or `<Action.<Type>>`)*
Attached as a child of `<InputSelectList>` to trigger updates on user interaction.
* **`<EventHandler DhtmlEvent="onchange">`**: Attaches an `onchange` handler to the parent input.
* **`<Action Type="RefreshElement" ElementID="<TargetID>">`**: Serializes the enclosing `<Form>` (or standalone input), updates the browser URL via `history.pushState()`, sends an asynchronous `fetch()` request with `?rdAjaxRefresh=<TargetID>`, and swaps `<div id="<TargetID>_wrapper">` in place without a full page reload.
* **`<Action Type="Report">`**: Triggers a standard full-page form submission (`this.form.submit()`).

```xml
<Form ID="frmProductFilters" Method="GET">
  <InputHidden ID="rdReport" DefaultValue="ProductsCatalogReport" />
  <InputSelectList ID="CategoryID"
                   Caption="Category:"
                   OptionCaptionColumn="category_name"
                   OptionValueColumn="category_id"
                   IncludeBlank="True"
                   IncludeBlankCaption="-- All Categories --">
    <DataLayer Type="SQL" ConnectionID="connMyGuitarShop" Source="SELECT category_id, category_name FROM categories" />
    <EventHandler DhtmlEvent="onchange">
      <Action Type="RefreshElement" ElementID="dtProducts" />
    </EventHandler>
  </InputSelectList>
  <InputText ID="SearchTerm" Caption="Search:" DefaultValue="" />
  <Button ID="btnFilter" Caption="Apply Filter" />
</Form>
```

---

## 6. Data Access (`<DataLayer>`)

Supported as a direct child of `<DataTable>`, `<ChartCanvas>`, `<Series>`, or `<InputSelectList>`. Both `<DataLayer ... Type="SQL">` and `<DataLayer.SQL ...>` forms are supported.

### SQL DataLayer (`Type="SQL"`)
* **`ID`**: DataLayer identifier.
* **`ConnectionID`** *(required)*: References a `<Connection ID="...">` defined in `_Settings.lgx`.
* **`Source`** *(required)*: SQL query string (supports `@Request.*~`, `@Session.*~`, and `@Constant.*~` token interpolation as well as `:param` placeholders).

### Prepared SQL Parameters (`<SqlParameters>` & `<SqlParameter>`)
Optional children of `<DataLayer Type="SQL">` that bind parameters safely using PDO prepared statements.
* **`ID`** *(required)*: Named placeholder matching `:ID` in the SQL `Source` query (leading `:` or `@` characters are stripped automatically before binding).
* **`Value`** *(required)*: Value or token expression to bind (e.g., `@Request.CategoryID~`).
* **`DataType`**:
  * `Number`: Casts to `int` and binds as `PDO::PARAM_INT`.
  * `Text` *(default)*: Binds as `PDO::PARAM_STR`.

```xml
<DataLayer Type="SQL"
           ID="dlProducts"
           ConnectionID="connMyGuitarShop"
           Source="SELECT * FROM products WHERE (:cat_id = 0 OR category_id = :cat_id)">
  <SqlParameters>
    <SqlParameter ID="cat_id" Value="@Request.CategoryID~" DataType="Number" />
  </SqlParameters>
</DataLayer>
```

### Static DataLayer (`Type="Static"`)
* **`ID`**: DataLayer identifier.
* **Child `<StaticDataRow .../>`**: Each attribute name/value pair becomes a column/value in the dataset row.

---

## 7. DataTable & Interactive Controls

### `<DataTable>`
Renders an HTML `<table>` wrapped in `<div id="<ID>_wrapper">` for AJAX partial updates. Active query parameters (such as filters) are automatically preserved across sort and pagination links.
* **`ID`**: Unique table identifier (used to namespace sort and paging query parameters).
* **`AjaxPaging`**: `True` | `False`. Intercepts sort and page clicks via `fetch()` and replaces `<ID>_wrapper` in place without a full page reload.
* **`Class`**: Table CSS class (e.g., `ThemeDataTable`).
* **`AltRowClass`**: CSS class applied to odd-indexed body rows (e.g., `ThemeAlternatingRow`).
* **`BorderColor`**: CSS border color applied to header and data cells (default: `#CCCCCC`).
* **`CellPadding`**: Cell padding in pixels (default: `4`).
* **`CellSpacing`**: HTML `cellspacing` attribute (default: `0`).
* **`Width`** & **`WidthScale`**: Table width value and unit (e.g., `Width="100" WidthScale="%"`).

### `<DataTableColumn>` *(Child of `<DataTable>`)*
Defines a column header and renders its child elements for every row in the dataset.
* **`ID`**: Column identifier.
* **`Header`**: Column header text (supports token resolution).
* **`Class`**: CSS class applied to both `<th>` and `<td>` cells (e.g., `ThemeAlignRight`).

### `<DataColumnSort>` *(Child of `<DataTableColumn>`)*
Makes the parent column header clickable to toggle ascending/descending sorting.
* **`DataColumn`** *(required)*: Dataset field name to sort by.
* **`DataType`**: `Text` (case-insensitive natural sort, default) | `Number` (numeric float comparison).

### `<InteractivePaging>` *(Child of `<DataTable>`)*
Slices the dataset into pages and renders numbered navigation controls.
* **`PageRowCount`**: Number of rows per page (default: `20`).
* **`ShowPageNumber`**: `Numbered` (renders `« Prev`, numbered page buttons, and `Next »`).
* **`Location`**: `Bottom` (default) | `Top` | `Both`.
* **`CaptionType`**: `Text`.

---

## 8. Charts (`<ChartCanvas>`)

Renders an inline SVG column/bar chart with automatic nice-step Y-axis scaling, horizontal gridlines, hover tooltips (`<title>`), and optional value labels. Aliased to `<ChartCanvas>`, `<Chart.XY>`, and `<Chart>`.

### `<ChartCanvas>`
* **`ID`**: SVG element identifier (wrapper uses `<ID>_wrapper`).
* **`ChartWidth`**: Width in pixels (default: `650`, minimum: `200`).
* **`ChartHeight`**: Height in pixels (default: `350`, minimum: `150`).
* **`ChartCaption`**: Chart title centered above the plot area.

### `<Series>` *(or `<Series.Bar>`)*
* **`Type`**: `Bar` (renders vertical columns).
* **`ChartXDataColumn`** *(or `XDataColumn`)*: Dataset column for X-axis categories.
* **`ChartYDataColumn`** *(or `YDataColumn`)*: Dataset column for numeric bar heights (matched case-insensitively).
* **`Color`**: Hex or CSS fill color for bars (default: `#2b6cb0`).

### `<DataLabels>` *(Child of `<Series>`)*
* Presence of `<DataLabels/>` renders numeric values centered above each bar.

### `<ChartXAxis>` & `<ChartYAxis>` *(Children of `<ChartCanvas>`)*
* **`AxisCaption`**: Axis title rendered below the X-axis or rotated along the Y-axis.

---

## 9. Built-In Theme CSS Classes & Runtime URL Parameters

### Supported Theme Classes (`ReportRenderer`)
* **`ThemeTextLargest`**: `1.5rem`, semi-bold heading style.
* **`ThemeDataTable`**: Collapsed-border table layout with shaded `<th>` headers.
* **`ThemeAlternatingRow`**: Subtle alternating row background (`#f9fafb`).
* **`ThemeAlignRight`**: Right-aligns cell text and flex-aligns sortable header links.

### Runtime URL Query Parameters (`index.php`)
* **`?rdReport=<Slug>`**: Loads `reports/<Slug>.lgx` (omitting renders the categorized Reports Directory).
* **`?rdAjaxRefresh=<ElementID>`**: Returns only the rendered HTML fragment for `<ElementID>` (used automatically by `AjaxPaging="True"` and `Action Type="RefreshElement"`).
* **`?<TableID>_sort=<Column>` & `?<TableID>_dir=ASC|DESC`**: Controls active column sorting per `DataTable`.
* **`?<TableID>_page=<PageNumber>`**: Controls active page index per `DataTable`.
* **`?<InputID>=<Value>`**: Populates `@Request.<InputID>~` across reports, SQL parameters, and form controls.