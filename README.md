# PHPLogiInfoParser
A lightweight, extensible PHP 8+ rendering engine for Logi Info (.lgx) report definitions. Parses Logi XML reports, _Settings.lgx connections, and metadata catalogs to render interactive data tables, AJAX paging/sorting, and SVG charts without proprietary runtime dependencies.

LogiPHP is an open-source PHP 8+ runtime engine designed to parse and render declarative Logi Info (.lgx) report definitions. Built around an extensible element-renderer registry, it supports _Settings.lgx connection pooling, @Data/@Request/@Session/@Constant token resolution, sortable and AJAX-paginated DataTable grids, inline SVG ChartCanvas visualizations, and role-filtered report directory catalogs.

1. Application Settings (_Settings.lgx)
The _Settings.lgx file defines global constants and named database connections loaded lazily at runtime via ConnectionManager::loadSettingsFile().

<Setting> (Root)
ID: Identifier for the settings file (e.g., _Settings).

<Constants> & <Constant>
Defines global key-value pairs accessible anywhere in report attributes via @Constant.<ID>~.

ID (required): Constant name.

Value (required): String value substituted when resolved.

<Connections> & <Connection> (or <Connection.<Type>>)
Defines named data sources referenced by DataLayer elements via ConnectionID. Both <Connection ... Type="MySQL"/> and <Connection.MySQL .../> syntax forms are supported.

ID (required): Connection identifier (e.g., connMyGuitarShop).

Type: Database driver (MySQL, MariaDB, PostgreSQL/Postgres/PgSQL, SqlServer/MSSQL, SQLite, or PDO).

Server (or Host): Database hostname or IP address (default: 127.0.0.1).

Port: TCP port (defaults: 3306 for MySQL, 5432 for PostgreSQL).

Database (or DbName): Database name, or file path for SQLite.

User (or Username): Database username.

Password: Database password.

Charset: Character encoding for MySQL/MariaDB (default: utf8mb4).

Dsn: Full raw PDO DSN string when Type="PDO".

2. Token Resolution (LogiContext)
Tokens use Logi Info's @Scope.Key~ syntax and are resolved dynamically inside text attributes (Caption, Header, ChartCaption, AxisCaption, Source, etc.).

Token Syntax	Source	Escaping Behavior
@Data.<ColumnName>~	Current row in an active DataTable iteration	HTML-escaped (ENT_QUOTES, UTF-8)
@Request.<ParamName>~	HTTP query/request parameters ($_GET)	HTML-escaped in UI attributes; unescaped in SQL Source
@Session.<KeyName>~	Active PHP session variables ($_SESSION)	HTML-escaped in UI attributes; unescaped in SQL Source
@Constant.<ID>~	Global <Constant> definitions in _Settings.lgx	HTML-escaped in UI attributes; unescaped in SQL Source
3. Report Structure & Catalog Metadata (.lgx)
<Report> (Root)
ID: Internal report identifier (defaults to filename if omitted).

Caption (or Title): Display title used in <title> and the Reports Directory portal.

Category: Grouping heading in the Reports Directory (defaults to the parent subfolder name or General).

Description: Summary text displayed on the report card in the directory portal.

SecurityRightID: Comma-separated list of required roles/rights (e.g., SalesUser,Admin). If specified, the report is only listed when the user holds at least one matching right.

ShowInMenu: True | False (default: True). Controls visibility in ReportCatalog::listReports().

IdeDisplay: Supported on any element; setting IdeDisplay="False" suppresses rendering of that element and its children.

<ReportMeta> (Optional Child of <Report>)
Ignored during report HTML rendering; read by ReportCatalog for portal ordering and UI hints.

SortOrder: Integer sort weight within a category (default: 100).

Icon: Icon identifier string stored in catalog metadata.

<Body> & <ideTestParams>
<Body>: Primary container for visual report elements.

<ideTestParams>: Logi Studio design-time parameter block; safely ignored at runtime.

4. Layout & Text Elements
<Division>
Renders a structural HTML container (<div> or <span>) and recursively renders child elements.

ID: HTML id attribute.

Class: HTML class attribute.

HtmlDiv: True renders a <div>; False or omitted renders a <span>.

<Label>
Renders text or HTML content with token substitution.

ID: HTML id attribute.

Caption: Text or token expression to display.

Class: CSS class name(s).

Format: Set to HTML to output unescaped HTML markup; otherwise HTML-escaped inside a <span>.

<LineBreak>
LineCount: Number of <br> tags to emit (default: 1).

5. Data Access (<DataLayer>)
Supported as a direct child of <DataTable>, <ChartCanvas>, or <Series>. Both <DataLayer ... Type="SQL"> and <DataLayer.SQL ...> forms are supported.

SQL DataLayer (Type="SQL")
ID: DataLayer identifier.

ConnectionID (required): References a <Connection ID="..."> defined in _Settings.lgx.

Source (required): SQL query string (supports @Request.*~, @Session.*~, and @Constant.*~ token interpolation).

Static DataLayer (Type="Static")
ID: DataLayer identifier.

Child <StaticDataRow .../>: Each attribute name/value pair becomes a column/value in the dataset row.

6. DataTable & Interactive Controls
<DataTable>
Renders an HTML <table> wrapped in <div id="<ID>_wrapper"> for AJAX partial updates.

ID: Unique table identifier (used to namespace sort and paging query parameters).

AjaxPaging: True | False. Intercepts sort and page clicks via fetch() and replaces <ID>_wrapper in place without a full page reload.

Class: Table CSS class (e.g., ThemeDataTable).

AltRowClass: CSS class applied to odd-indexed body rows (e.g., ThemeAlternatingRow).

BorderColor: CSS border color applied to header and data cells (default: #CCCCCC).

CellPadding: Cell padding in pixels (default: 4).

CellSpacing: HTML cellspacing attribute (default: 0).

Width & WidthScale: Table width value and unit (e.g., Width="100" WidthScale="%").

<DataTableColumn> (Child of <DataTable>)
Defines a column header and renders its child elements for every row in the dataset.

ID: Column identifier.

Header: Column header text (supports token resolution).

Class: CSS class applied to both <th> and <td> cells (e.g., ThemeAlignRight).

<DataColumnSort> (Child of <DataTableColumn>)
Makes the parent column header clickable to toggle ascending/descending sorting.

DataColumn (required): Dataset field name to sort by.

DataType: Text (case-insensitive natural sort, default) | Number (numeric float comparison).

<InteractivePaging> (Child of <DataTable>)
Slices the dataset into pages and renders numbered navigation controls.

PageRowCount: Number of rows per page (default: 20).

ShowPageNumber: Numbered (renders « Prev, numbered page buttons, and Next »).

Location: Bottom (default) | Top | Both.

CaptionType: Text.

7. Charts (<ChartCanvas>)
Renders an inline SVG column/bar chart with automatic nice-step Y-axis scaling, horizontal gridlines, hover tooltips (<title>), and optional value labels. Aliased to <ChartCanvas>, <Chart.XY>, and <Chart>.

<ChartCanvas>
ID: SVG element identifier (wrapper uses <ID>_wrapper).

ChartWidth: Width in pixels (default: 650, minimum: 200).

ChartHeight: Height in pixels (default: 350, minimum: 150).

ChartCaption: Chart title centered above the plot area.

<Series> (or <Series.Bar>)
Type: Bar (renders vertical columns).

ChartXDataColumn (or XDataColumn): Dataset column for X-axis categories.

ChartYDataColumn (or YDataColumn): Dataset column for numeric bar heights (matched case-insensitively).

Color: Hex or CSS fill color for bars (default: #2b6cb0).

<DataLabels> (Child of <Series>)
Presence of <DataLabels/> renders numeric values centered above each bar.

<ChartXAxis> & <ChartYAxis> (Children of <ChartCanvas>)
AxisCaption: Axis title rendered below the X-axis or rotated along the Y-axis.

8. Built-In Theme CSS Classes & Runtime Parameters
Supported Theme Classes (ReportRenderer)
ThemeTextLargest: 1.5rem, semi-bold heading style.

ThemeDataTable: Collapsed-border table layout with shaded <th> headers.

ThemeAlternatingRow: Subtle alternating row background (#f9fafb).

ThemeAlignRight: Right-aligns cell text and flex-aligns sortable header links.

Runtime URL Query Parameters (index.php)
?rdReport=<Slug>: Loads reports/<Slug>.lgx (omitting renders the categorized Reports Directory).

?rdAjaxRefresh=<ElementID>: Returns only the rendered HTML fragment for <ElementID> (used automatically by AjaxPaging="True").

?<TableID>_sort=<Column> & ?<TableID>_dir=ASC|DESC: Controls active column sorting per DataTable.

?<TableID>_page=<PageNumber>: Controls active page index per DataTable.
