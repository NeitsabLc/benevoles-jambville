import { startStimulusApp } from '@symfony/stimulus-bundle';
import FloatingPopoverController from './controllers/floating_popover_controller.js';
import RoomingScrollController from './controllers/rooming_scroll_controller.js';
import SearchableSelectController from './controllers/searchable_select_controller.js';

const app = startStimulusApp();
app.register('floating-popover', FloatingPopoverController);
app.register('rooming-scroll', RoomingScrollController);
app.register('searchable-select', SearchableSelectController);
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
