import AdminController from './AdminController';
import Auth from './Auth';
import BacChairmanController from './BacChairmanController';
import BacSecretariatController from './BacSecretariatController';
import DocumentViewController from './DocumentViewController';
import HopeController from './HopeController';
import NotificationController from './NotificationController';
import ProcurementController from './ProcurementController';
import SearchController from './SearchController';
import Settings from './Settings';
import ViewProcurementsController from './ViewProcurementsController';
const Controllers = {
    Auth: Object.assign(Auth, Auth),
    SearchController: Object.assign(SearchController, SearchController),
    BacSecretariatController: Object.assign(BacSecretariatController, BacSecretariatController),
    ViewProcurementsController: Object.assign(ViewProcurementsController, ViewProcurementsController),
    ProcurementController: Object.assign(ProcurementController, ProcurementController),
    BacChairmanController: Object.assign(BacChairmanController, BacChairmanController),
    HopeController: Object.assign(HopeController, HopeController),
    AdminController: Object.assign(AdminController, AdminController),
    NotificationController: Object.assign(NotificationController, NotificationController),
    DocumentViewController: Object.assign(DocumentViewController, DocumentViewController),
    Settings: Object.assign(Settings, Settings),
};

export default Controllers;
