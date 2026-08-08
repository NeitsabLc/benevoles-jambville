import { startStimulusApp } from '@symfony/stimulus-bundle';
import SearchableSelectController from './controllers/searchable_select_controller.js';

const app = startStimulusApp();
app.register('searchable-select', SearchableSelectController);
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
