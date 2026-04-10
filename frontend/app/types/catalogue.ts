/** A course as returned by `get_catalogue_data`. */
export interface CatalogueCourse {
  id: number
  fullname: string
  image: string
  isenrolled: boolean
  categoryid: number
  categoryname: string
  duration_hours: number
  activitycount: number
}

/** SmartMind category for the filter badge row. */
export interface CatalogueCategory {
  id: number
  name: string
  imageurl: string
}

/** Root response from `get_catalogue_data`. */
export interface CatalogueData {
  courses: CatalogueCourse[]
  categories: CatalogueCategory[]
  hascategories: boolean
}
