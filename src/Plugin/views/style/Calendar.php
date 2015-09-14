<?php

/**
 * @file
 * Contains \Drupal\calendar\Plugin\views\style\Calendar.
 */

namespace Drupal\calendar\Plugin\views\style;

use Drupal\calendar\Util\CalendarHelper;
use Drupal\calendar\Plugin\views\row\Calendar as CalendarRow;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\ViewExecutable;

/**
 * Views style plugin for the Calendar module.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "calendar",
 *   title = @Translation("Calendar"),
 *   help = @Translation("Present view results as a Calendar."),
 *   theme = "calendar_style",
 *   display_types = {"normal"},
 *   even_empty = TRUE
 * )
 */
class Calendar extends StylePluginBase {

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * Does the style plugin allows to use style plugins.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Does the style plugin support grouping of rows.
   *
   * @var bool
   */
  protected $usesGrouping = FALSE;


  protected $dateInfo;
  protected $items;
  protected $currentDay;

  /**
   * Overrides \Drupal\views\Plugin\views\style\StylePluginBase::init().
   *
   * @todo Document why we override.
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    if (empty($view->dateInfo)) {
      // @todo This should become a dedicated dateInfo class.
      $this->dateInfo = new \stdClass();
    }
    //$this->dateInfo = &$this->view->dateInfo;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['calendar_type'] = ['default' => 'month'];
    $options['name_size'] = ['default' => 3];
    $options['mini'] = ['default' => 0];
    $options['with_weekno'] = ['default' => 0];
    $options['multiday_theme'] = ['default' => 1];
    $options['theme_style'] = ['default' => 1];
    $options['max_items'] = ['default' => 0];
    $options['max_items_behavior'] = ['default' => 'more'];
    $options['groupby_times'] = ['default' => 'hour'];
    $options['groupby_times_custom'] = ['default' => ''];
    $options['groupby_field'] = ['default' => ''];
    $options['multiday_hidden'] = ['default' => []];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['calendar_type'] = [
      '#title' => $this->t('Calendar type'),
      '#type' => 'select',
      '#description' => $this->t('Select the calendar time period for this display.'),
      '#default_value' => $this->options['calendar_type'],
      '#options' => calendar_display_types(),
    ];
    $form['mini'] = [
      '#title' => $this->t('Display as mini calendar'),
      '#default_value' => $this->options['mini'],
      '#type' => 'radios',
      '#options' => [
        0 => $this->t('No'),
        1 => $this->t('Yes'),
      ],
      '#description' => $this->t('Display the mini style calendar, with no item details. Suitable for a calendar displayed in a block.'),
      '#dependency' => ['edit-style-options-calendar-type' => ['month']],
    ];
    $form['name_size'] = [
      '#title' => $this->t('Calendar day of week names'),
      '#default_value' => $this->options['name_size'],
      '#type' => 'radios',
      '#options' => [
        1 => $this->t('First letter of name'),
        2 => $this->t('First two letters of name'),
        3 => $this->t('Abbreviated name'),
        99 => $this->t('Full name'),
      ],
      '#description' => $this->t('The way day of week names should be displayed in a calendar.'),
      '#dependency' => [
        'edit-style-options-calendar-type' => ['month', 'week', 'year'],
      ],
    ];
    $form['with_weekno'] = [
      '#title' => $this->t('Show week numbers'),
      '#default_value' => $this->options['with_weekno'],
      '#type' => 'radios',
      '#options' => [
        0 => $this->t('No'),
        1 => $this->t('Yes'),
      ],
      '#description' => $this->t('Whether or not to show week numbers in the left column of calendar weeks and months.'),
      '#dependency' => ['edit-style-options-calendar-type' => ['month']],
    ];
    $form['max_items'] = [
      '#title' => $this->t('Maximum items'),
      '#type' => 'select',
      '#options' => [
        0 => $this->t('Unlimited'),
        1 => $this->formatPlural(1, '1 item', '@count items'),
        3 => $this->formatPlural(3, '1 item', '@count items'),
        5 => $this->formatPlural(5, '1 item', '@count items'),
        10 => $this->formatPlural(10, '1 item', '@count items'),
      ],
      '#default_value' => $this->options['calendar_type'] != 'day' ? $this->options['max_items'] : 0,
      '#description' => $this->t('Maximum number of items to show in calendar cells, used to keep the calendar from expanding to a huge size when there are lots of items in one day.'),
      '#dependency' => ['edit-style-options-calendar-type' => ['month']],
    ];
    $form['max_items_behavior'] = [
      '#title' => $this->t('Too many items'),
      '#type' => 'select',
      '#options' => [
        'more' => $this->t("Show maximum, add 'more' link"),
        'hide' => $this->t('Hide all, add link to day'),
      ],
      '#default_value' => $this->options['calendar_type'] != 'day' ? $this->options['max_items_behavior'] : 'more',
      '#description' => $this->t('Behavior when there are more than the above number of items in a single day. When there more items than this limit, a link to the day view will be displayed.'),
      '#dependency' => ['edit-style-options-calendar-type' => ['month']],
    ];
    $form['groupby_times'] = [
      '#title' => $this->t('Time grouping'),
      '#type' => 'select',
      '#default_value' => $this->options['groupby_times'],
      '#description' => $this->t("Group items together into time periods based on their start time."),
      '#options' => [
        '' => $this->t('None'),
        'hour' => $this->t('Hour'),
        'half' => $this->t('Half hour'),
        'custom' => $this->t('Custom'),
      ],
      '#dependency' => [
        'edit-style-options-calendar-type' => ['day', 'week'],
      ],
    ];
    $form['groupby_times_custom'] = [
      '#title' => $this->t('Custom time grouping'),
      '#type' => 'textarea',
      '#default_value' => $this->options['groupby_times_custom'],
      '#description' => $this->t("When choosing the 'custom' Time grouping option above, create custom time period groupings as a comma-separated list of 24-hour times in the format HH:MM:SS, like '00:00:00,08:00:00,18:00:00'. Be sure to start with '00:00:00'. All items after the last time will go in the final group."),
      '#dependency' => ['edit-style-options-groupby-times' => ['custom']],
    ];
    $form['theme_style'] = [
      '#title' => $this->t('Overlapping time style'),
      '#default_value' => $this->options['theme_style'],
      '#type' => 'select',
      '#options' => [
        0 => $this->t('Do not display overlapping items'),
        1 => $this->t('Display overlapping items, with scrolling'),
        2 => $this->t('Display overlapping items, no scrolling'),
      ],
      '#description' => $this->t('Select whether calendar items are displayed as overlapping items. Use scrolling to shrink the window and focus on the selected items, or omit scrolling to display the whole day. This only works if hour or half hour time grouping is chosen!'),
      '#dependency' => [
        'edit-style-options-calendar-type' => ['day', 'week'],
      ],
    ];

    // Create a list of fields that are available for grouping.
    $field_options = [];
    $fields = $this->view->display_handler->getOption('fields');
    foreach ($fields as $field_name => $field) {
      $field_options[$field_name] = $field['field'];
    }
    $form['groupby_field'] = [
      '#title' => $this->t('Field grouping'),
      '#type' => 'select',
      '#default_value' => $this->options['groupby_field'],
      '#description' => $this->t("Optionally group items into columns by a field value, for instance select the content type to show items for each content type in their own column, or use a location field to organize items into columns by location. NOTE! This is incompatible with the overlapping style option."),
      '#options' => ['' => ''] + $field_options,
      '#dependency' => ['edit-style-options-calendar-type' => ['day']],
    ];
    $form['multiday_theme'] = [
      '#title' => $this->t('Multi-day style'),
      '#default_value' => $this->options['multiday_theme'],
      '#type' => 'select',
      '#options' => [
        0 => $this->t('Display multi-day item as a single column'),
        1 => $this->t('Display multi-day item as a multiple column row')
      ],
      '#description' => $this->t('If selected, items which span multiple days will displayed as a multi-column row.  If not selected, items will be displayed as an individual column.'),
      '#dependency' => [
        'edit-style-options-calendar-type' => ['month', 'week'],
      ],
    ];
    $form['multiday_hidden'] = [
      '#title' => $this->t('Fields to hide in Multi-day rows'),
      '#default_value' => $this->options['multiday_hidden'],
      '#type' => 'checkboxes',
      '#options' => $field_options,
      '#description' => $this->t('Choose fields to hide when displayed in multi-day rows. Usually you only want to see the title or Colorbox selector in multi-day rows and would hide all other fields.'),
      '#dependency' => [
        'edit-style-options-calendar-type' => ['month', 'week', 'day'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    $groupby_times = $form_state->getValue(['style_options', 'groupby_times']);
    if ($groupby_times == 'custom' && $form_state->isValueEmpty(['style_options', 'groupby_times_custom'])) {
      $form_state->setErrorByName('groupby_times_custom', $this->t('Custom groupby times cannot be empty.'));
    }
    if ((!$form_state->isValueEmpty(['style_options', 'theme_style']) && empty($groupby_times)) || !in_array($groupby_times, ['hour', 'half'])) {
      $form_state->setErrorByName('theme_style', $this->t('Overlapping items only work with hour or half hour groupby times.'));
    }
    if (!$form_state->isValueEmpty(['style_options', 'theme_style']) && !$form_state->isValueEmpty(['style_options', 'group_by_field'])) {
      $form_state->setErrorByName('theme_style', $this->t('ou cannot use overlapping items and also try to group by a field value.'));
    }
    if ($groupby_times != 'custom') {
      $form_state->setValue(['style_options', 'groupby_times_custom'], NULL);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    $multiday_hidden = $form_state->getValue(['style_options', 'multiday_hidden']);
    $form_state->setValue(['style_options', 'multiday_hidden'], array_filter($multiday_hidden));
  }

  /**
   * Helper function to find the date argument handler for this view.
   */
  protected function dateArgumentHandler() {
    // @todo Fix this, check core/modules/datetime/datetime.views.inc.
    $i = 0;
    foreach ($this->view->argument as $name => $handler) {
      if (date_views_handler_is_date($handler, 'argument')) {
        $this->dateInfo->date_arg_pos = $i;
        return $handler;
      }
      $i++;
    }
    return FALSE;
  }

  /**
   * Inspect argument and view information to see which calendar period we
   * should show. The argument tells us what to use if there is no value, the
   * view args tell us what to use if there are values.
   */
  protected function granularity() {
    // @todo Document this.
    if (!$handler = $this->dateArgumentHandler()) {
      return 'month';
    }
    $default_granularity = !empty($handler) && !empty($handler->granularity) ? $handler->granularity : 'month';
    $wildcard = !empty($handler) ? $handler->options['exception']['value'] : '';
    $argument = $handler->argument;

    // @todo Anything else we need to do for 'all' arguments?
    if ($argument == $wildcard) {
      $this->view_granularity = $default_granularity;
    }
    elseif (!empty($argument)) {
      module_load_include('inc', 'date_api', 'date_api_sql');

      $date_handler = new date_sql_handler();
      $this->view_granularity = $date_handler->arg_granularity($argument);
    }
    else {
      $this->view_granularity = $default_granularity;
    }
    return $this->view_granularity;
  }

  /**
   * @todo Document this.
   */
  protected function hasCalendarRowPlugin() {
    return $this->view->rowPlugin instanceof CalendarRow;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    if (empty($this->view->rowPlugin) || !$this->hasCalendarRowPlugin()) {
      debug('\Drupal\calendar\Plugin\views\style\CalendarStyle: The calendar row plugin is required when using the calendar style, but it is missing.');
      return;
    }
    if (!$argument = $this->dateArgumentHandler()) {
      debug('\Drupal\calendar\Plugin\views\style\CalendarStyle: A date argument is required when using the calendar style, but it is missing or is not using the default date.');
      return;
    }

    // There are date arguments that have not been added by Date Views.
    // They will be missing the information we would need to render the field.
    // @todo uncomment this when we find a fix for the date range issue.
//    if (empty($argument->min_date)) {
//      return;
//    }
    $argument->min_date = new \DateTime('-3 months');
    $argument->max_date = new \DateTime('+3 months');

    // Add information from the date argument to the view.
    $this->dateInfo->granularity = $this->granularity();
    $this->dateInfo->calendar_type = $this->options['calendar_type'];
    $this->dateInfo->date_arg = $argument->argument;
    // @todo Can we fix these with formatters?
    $this->dateInfo->year = date_format($argument->min_date, 'Y');
    $this->dateInfo->month = date_format($argument->min_date, 'n');;
    $this->dateInfo->day = date_format($argument->min_date, 'j');
    // @todo We shouldn't use DATETIME_DATE_STORAGE_FORMAT.
    $this->dateInfo->week = date_week(date_format($argument->min_date, DATETIME_DATE_STORAGE_FORMAT));
    // @todo implement date range
//    $this->dateInfo->date_range = $argument->date_range;
    $this->dateInfo->min_date = $argument->min_date;
    $this->dateInfo->max_date = $argument->max_date;
    // @todo implement limit
//    $this->dateInfo->limit = $argument->limit;
    // @todo What if the display doesn't have a route?
    //$this->dateInfo->url = $this->view->getUrl();
//    $this->dateInfo->min_date_date = date_format($this->dateInfo->min_date, DATETIME_DATE_STORAGE_FORMAT);
//    $this->dateInfo->max_date_date = date_format($this->dateInfo->max_date, DATETIME_DATE_STORAGE_FORMAT);
    $this->dateInfo->forbid = isset($argument->forbid) ? $argument->forbid : FALSE;

    // Add calendar style information to the view.
    $this->dateInfo->calendar_popup = $this->displayHandler->getOption('calendar_popup');
    $this->dateInfo->style_name_size = $this->options['name_size'];
    $this->dateInfo->mini = $this->options['mini'];
    $this->dateInfo->style_with_weekno = $this->options['with_weekno'];
    $this->dateInfo->style_multiday_theme = $this->options['multiday_theme'];
    $this->dateInfo->style_theme_style = $this->options['theme_style'];
    $this->dateInfo->style_max_items = $this->options['max_items'];
    $this->dateInfo->style_max_items_behavior = $this->options['max_items_behavior'];
    if (!empty($this->options['groupby_times_custom'])) {
      $this->dateInfo->style_groupby_times = explode(',', $this->options['groupby_times_custom']);
    }
    else {
      $this->dateInfo->style_groupby_times = calendar_groupby_times($this->options['groupby_times']);
    }
    $this->dateInfo->style_groupby_field = $this->options['groupby_field'];

    // TODO make this an option setting.
    $this->dateInfo->style_show_empty_times = !empty($this->options['groupby_times_custom']) ? TRUE : FALSE;

    // Set up parameters for the current view that can be used by the row plugin.
    $display_timezone = date_timezone_get($this->dateInfo->min_date);
    $this->dateInfo->display_timezone = $display_timezone;
    $this->dateInfo->display_timezone_name = timezone_name_get($display_timezone);

    $date = clone($this->dateInfo->min_date);

    date_timezone_set($date, $display_timezone);
    $this->dateInfo->min_zone_string = date_format($date, DATETIME_DATE_STORAGE_FORMAT);
    $date = clone($this->dateInfo->max_date);
    date_timezone_set($date, $display_timezone);
    $this->dateInfo->max_zone_string = date_format($date, DATETIME_DATE_STORAGE_FORMAT);

    // Let views render fields the way it thinks they should look before we
    // start massaging them.
    $this->renderFields($this->view->result);

    // Invoke the row plugin to massage each result row into calendar items.
    // Gather the row items into an array grouped by date and time.
    $items = [];
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows = $this->view->rowPlugin->preRender($row);
      // @todo Check what comes out here.
//      foreach ($rows as $key => $item) {
//        $item->granularity = $this->dateInfo->granularity;
//        $rendered_fields = array();
//        $item_start = date_format($item->calendar_start_date, DATE_FORMAT_DATE);
//        $item_end = date_format($item->calendar_end_date, DATE_FORMAT_DATE);
//        $time_start = date_format($item->calendar_start_date, 'H:i:s');
//        $item->rendered_fields = $this->rendered_fields[$row_index];
//        $items[$item_start][$time_start][] = $item;
//      }
    }

    ksort($items);

    $rows = [];
    $this->currentDay = clone($this->dateInfo->min_date);
    $this->items = $items;

    // Retrieve the results array using a the right method for the granularity of the display.
    switch ($this->options['calendar_type']) {
      case 'year':
        $rows = [];
        $this->dateInfo->mini = TRUE;
        for ($i = 1; $i <= 12; $i++) {
          $rows[$i] = $this->calendarBuildMiniMonth();
        }
        $this->dateInfo->mini = FALSE;
        break;
      case 'month':
        $rows = !empty($this->dateInfo->mini) ? $this->calendarBuildMiniMonth() : $this->calendarBuildMonth();
        break;
      case 'day':
        $rows = $this->calendarBuildDay();
        break;
      case 'week':
        $rows = $this->calendarBuildWeek();
        // Merge the day names in as the first row.
        $rows = array_merge([calendar_week_header($this->view)], $rows);
        break;
    }

    // Send the sorted rows to the right theme for this type of calendar.
    $this->definition['theme'] = 'calendar_' . $this->options['calendar_type'];

    // Adjust the theme to match the currently selected default.
    // Only the month view needs the special 'mini' class,
    // which is used to retrieve a different, more compact, theme.
    if ($this->options['calendar_type'] == 'month' && !empty($this->dateInfo->mini)) {
      $this->definition['theme'] = 'calendar_mini';
    }
    // If the overlap option was selected, choose the overlap version of the theme.
    elseif (in_array($this->options['calendar_type'], ['week', 'day']) && !empty($this->options['multiday_theme']) && !empty($this->options['theme_style'])) {
      $this->definition['theme'] .= '_overlap';
    }

    $output = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => $rows,
    ];

    unset($this->view->row_index);
    return $output;
  }

  /**
   * Build one month.
   */
  public function calendarBuildMonth() {
    // @todo Implement.
    return 'month';
  }

  /**
   * Build one mini month.
   */
  public function calendarBuildMiniMonth() {
    $month = date_format($this->currentDay, 'n');
    date_modify($this->currentDay, '-' . strval(date_format($this->currentDay, 'j')-1) . ' days');
    $rows = [];
    // @todo the check in the while loop is not correct
//    do {
//      $rows = array_merge($rows, $this->calendarBuildMiniWeek());
//      $curday_date = date_format($this->currentDay, DATETIME_DATE_STORAGE_FORMAT);
//      $curday_month = date_format($this->currentDay, 'n');
//    } while ($curday_month == $month && $curday_date <= $this->dateInfo->max_date);
    // Merge the day names in as the first row.
    $rows = array_merge([CalendarHelper::weekHeader($this->view)], $rows);
    return $rows;
  }


  /**
   * Build one week row.
   */
  public function calendarBuildWeek() {
    // @todo Implement.
    return 'week';
  }

  /**
   * Build one mini week row.
   */
  public function calendarBuildMiniWeek() {
    // @todo Implement.
    return ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
  }

  /**
   * Build the contents of a single day for the $rows results.
   */
  public function calendarBuildWeekDay() {
    // @todo Implement.
    return 'weekday';
  }

  /**
   * Build the contents of a single day for the $rows results.
   *
   * @todo find out that the difference is with calendarBuildWeekDay() and
   * document accordingly.
   */
  public function calendarBuildDay() {
    // @todo Implement.
    return 'day';
  }
}
