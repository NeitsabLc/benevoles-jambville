import { startStimulusApp } from '@symfony/stimulus-bundle';
import ChambresDisponibiliteController from './controllers/chambres_disponibilite_controller.js';
import FloatingPopoverController from './controllers/floating_popover_controller.js';
import RoomingScrollController from './controllers/rooming_scroll_controller.js';
import SearchableSelectController from './controllers/searchable_select_controller.js';

const app = startStimulusApp();
app.register('chambres-disponibilite', ChambresDisponibiliteController);
app.register('floating-popover', FloatingPopoverController);
app.register('rooming-scroll', RoomingScrollController);
app.register('searchable-select', SearchableSelectController);
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
