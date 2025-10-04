import Auth from './Auth'
import SearchController from './SearchController'
import BacSecretariatController from './BacSecretariatController'
import ViewProcurementsController from './ViewProcurementsController'
import ProcurementController from './ProcurementController'
import BacChairmanController from './BacChairmanController'
import HopeController from './HopeController'
import AdminController from './AdminController'
import NotificationController from './NotificationController'
import DocumentViewController from './DocumentViewController'
import Settings from './Settings'
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
}

export default Controllers